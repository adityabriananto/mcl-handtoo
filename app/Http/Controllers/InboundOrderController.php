<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundUpload;
use App\Models\ApiLog;
use App\Models\InboundRequest;
use App\Models\InboundRequestDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InboundOrderController extends Controller
{
    //
    public function index(Request $request)
    {
        if ($request->has('reset')) {
            session()->forget('inbound_filters');
            return redirect()->route('inbound.index');
        }

        if ($request->isMethod('post')) {
            session(['inbound_filters' => $request->only(['search', 'warehouse', 'status'])]);
        }

        $filters = session('inbound_filters', []);

        // 1. Ambil SEMUA data tanpa pagination dulu untuk menghitung Statistik Operasional
        // Kita filter dulu agar statistik sinkron dengan filter yang dipilih
        $allData = InboundRequest::with('children')->filter($filters)->get();

        // 2. Hitung Statistik berdasarkan logika Split:
        // Hitung Child (jika ada) + Parent (yang tidak punya child)
        $operationalUnits = $allData->filter(function($item) {
            return $item->parent_id !== null || ($item->parent_id === null && $item->children->count() === 0);
        });

        $stats = (object)[
            'total'     => $operationalUnits->count(),
            'pending'   => $operationalUnits->where('status', 'Pending')->count(),
            'completed' => $operationalUnits->where('status', 'Completed')->count(),
        ];

        // 3. Ambil data untuk Tabel (Hanya Parent untuk tampilan utama)
        $requests = InboundRequest::with(['details', 'children.details'])
                ->filter($filters)
                ->whereNull('parent_id')
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'asc')
                ->paginate(50); // Gunakan angka lebih besar agar filter Alpine.js di View bekerja maksimal

        $warehouses = InboundRequest::distinct()->whereNotNull('warehouse_code')->pluck('warehouse_code');

        return view('inbound.index', compact('requests', 'warehouses', 'filters', 'stats'));
    }

    public function show($id)
    {
        // Mencari InboundRequest beserta detail SKU-nya
        $inbound = InboundRequest::with('details')->findOrFail($id);
        $totalQty = $inbound->details()->sum('requested_quantity');

        return view('inbound.show', compact('inbound','totalQty'));
    }

    public function split($id)
    {
        // 1. Ambil data original beserta detail SKU
        $original = InboundRequest::with('details')->findOrFail($id);
        $limit = 100;
        $totalQty = $original->details->sum('requested_quantity');

        if ($totalQty <= $limit) {
            return back()->with('error', 'Total kuantitas sudah di bawah limit.');
        }

        try {
            DB::beginTransaction();

            // 1. Tentukan Parent ID. Jika original sudah punya parent, gunakan itu.
            // Jika belum, maka original inilah yang jadi parent-nya.
            $parentId = $original->parent_id ?: $original->id;

            // 2. Kumpulkan semua baris SKU ke dalam pool (TANPA MENGHAPUS detail original)
            $skuPool = [];
            foreach ($original->details as $detail) {
                $skuPool[] = [
                    'seller_sku' => $detail->seller_sku,
                    'fulfillment_sku' => $detail->fulfillment_sku,
                    'requested_quantity' => (int) $detail->requested_quantity,
                ];
            }

            // 3. Hitung jumlah Child IO yang dibutuhkan
            $numberOfIOsNeeded = ceil($totalQty / $limit);

            for ($i = 1; $i <= $numberOfIOsNeeded; $i++) {
                // SEMUA iterasi membuat IO BARU sebagai Child.
                // IO Original tetap utuh dan tidak digunakan untuk menampung split.
                $childIO = $original->replicate();
                $childIO->parent_id = $parentId;

                // Cek urutan split berdasarkan jumlah child yang sudah ada
                $childCount = InboundRequest::where('parent_id', $parentId)->count() + 1;
                $childIO->reference_number = $original->reference_number . "-S" . str_pad($childCount, 2, '0', STR_PAD_LEFT);

                // Set status child menjadi Pending agar bisa diproses terpisah
                $childIO->status = 'Pending';
                $childIO->save();

                $currentIOCapacity = $limit;

                // 4. Isi Child IO dengan SKU dari pool
                foreach ($skuPool as &$sku) {
                    if ($currentIOCapacity <= 0) break;
                    if ($sku['requested_quantity'] <= 0) continue;

                    $take = min($sku['requested_quantity'], $currentIOCapacity);

                    // Buat baris detail untuk Child IO
                    $childIO->details()->create([
                        'seller_sku' => $sku['seller_sku'],
                        'fulfillment_sku' => $sku['fulfillment_sku'],
                        'requested_quantity' => $take,
                    ]);

                    // Kurangi sisa di pool dan kapasitas child saat ini
                    $sku['requested_quantity'] -= $take;
                    $currentIOCapacity -= $take;
                }
            }

            // PENTING: Update status Original IO (opsional)
            // Anda mungkin ingin menandai bahwa IO ini sudah diproses split-nya
            // $original->update(['status' => 'Splitted']);

            DB::commit();
            return back()->with('success', "Berhasil memecah menjadi " . $numberOfIOsNeeded . " dokumen baru. IO Original tetap utuh.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal split: ' . $e->getMessage());
        }
    }

    public function uploadIoNumber(Request $request) {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt,xls,xlsx|max:5120',
        ]);

        $file = $request->file('csv_file');
        $fileName = time() . '_' . $file->getClientOriginalName();

        // Simpan secara eksplisit ke disk 'local' (storage/app)
        $path = $file->storeAs('uploads', $fileName, 'local');

        // Ambil path absolut menggunakan facade Storage
        $fullPath = Storage::disk('local')->path($path);

        // Dispatch Job
        ProcessInboundUpload::dispatch($fullPath)->onQueue('io-number-upload');

        return back()->with('success', "File uploaded! Processing...");
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inbound_template.csv"',
        ];

        // Kolom hanya dua sesuai permintaan
        $columns = ['reference_number', 'inbound_order_no'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            // Contoh data dummy agar user mengerti formatnya
            fputcsv($file, ['REF2026010901', 'IO-MCL-99821']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function undoSplit($id)
    {
        $parent = InboundRequest::with('children.details')->findOrFail($id);

        try {
            DB::beginTransaction();

            foreach ($parent->children as $child) {
                foreach ($child->details as $detail) {
                    // Pindahkan kembali ke parent
                    $detail->update(['inbound_order_id' => $parent->id]);
                }
                // Hapus dokumen Sub-IO
                $child->delete();
            }

            DB::commit();
            return back()->with('success', 'Dokumen berhasil digabungkan kembali ke Main IO.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menggabungkan dokumen.');
        }
    }

    public function export(Request $request, $id)
    {
        $inbound = InboundRequest::with(['details', 'children.details'])->findOrFail($id);
        $vasNeeded = $request->vas_needed ?: '';
        $instruction = ($vasNeeded === 'Y') ? $request->vas_instruction : '';

        // LOGIKA BATCH EXPORT (ZIP)
        if ($request->type == 'batch' && $inbound->children->count() > 0) {
            $zipName = "Batch-Export-{$inbound->reference_number}.zip";
            $zip = new ZipArchive;
            $tempFile = tempnam(sys_get_temp_dir(), 'zip');

            if ($zip->open($tempFile, ZipArchive::CREATE) === TRUE) {
                foreach ($inbound->children as $child) {
                    $csvContent = fopen('php://temp', 'r+');
                    fputcsv($csvContent, ['Seller SKU', 'Request Quantity', 'Vas Needed', 'Repacking', 'Labeling', 'Bundling', 'No. of items to be bundled', 'Vas Instruction']);

                    // AGGREGATION: Group by SKU untuk baris Child
                    $uniqueSkus = $child->details->groupBy('seller_sku')->map(function ($row) {
                        return [
                            'seller_sku' => $row->first()->seller_sku,
                            'total_qty'  => $row->sum('requested_quantity'),
                        ];
                    });

                    foreach ($uniqueSkus as $item) {
                        fputcsv($csvContent, [
                            $item['seller_sku'],
                            $item['total_qty'],
                            $vasNeeded,
                            $request->repacking,
                            $request->labeling,
                            $request->bundling,
                            $request->bundle_items, // Pastikan nama variabel sesuai dengan modal (bundle_items)
                            $instruction
                        ]);
                    }

                    rewind($csvContent);
                    $zip->addFromString("Export-{$child->reference_number}.csv", stream_get_contents($csvContent));
                    fclose($csvContent);
                }
                $zip->close();
            }

            return response()->download($tempFile, $zipName)->deleteFileAfterSend(true);
        }

        // LOGIKA SINGLE EXPORT (CSV) - Dikelompokkan berdasarkan SKU Unik
        $filename = "Export-{$inbound->reference_number}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function() use ($inbound, $request, $vasNeeded, $instruction) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Seller SKU', 'Request Quantity', 'Vas Needed', 'Repacking', 'Labeling', 'Bundling', 'No. of items to be bundled', 'Vas Instruction']);

            // AGGREGATION: Group by SKU untuk Inbound Utama
            $uniqueSkus = $inbound->details->groupBy('seller_sku')->map(function ($row) {
                return [
                    'seller_sku' => $row->first()->seller_sku,
                    'total_qty'  => $row->sum('requested_quantity'),
                ];
            });

            foreach ($uniqueSkus as $item) {
                fputcsv($file, [
                    $item['seller_sku'],
                    $item['total_qty'],
                    $vasNeeded,
                    $request->repacking,
                    $request->labeling,
                    $request->bundling,
                    $request->bundle_items, // Sesuaikan dengan name di HTML (bundle_items)
                    $instruction
                ]);
            }
            fclose($file);
        }, 200, $headers);
    }

    public function exportChildren(InboundRequest $inbound)
    {
        // Pastikan ini adalah parent yang punya children
        $children = $inbound->children()->with('details')->get();

        if ($children->isEmpty()) {
            return back()->with('error', 'No child documents found.');
        }

        $fileName = 'Export_Children_' . $inbound->reference_number . '.csv';

        $response = new StreamedResponse(function () use ($children) {
            $handle = fopen('php://output', 'w');

            // Header CSV
            fputcsv($handle, ['Parent Ref', 'Child Ref', 'Warehouse', 'Seller SKU', 'Product Name', 'Quantity']);

            foreach ($children as $child) {
                foreach ($child->details as $detail) {
                    fputcsv($handle, [
                        $child->parent->reference_number,
                        $child->reference_number,
                        $child->warehouse_code,
                        $detail->seller_sku,
                        $detail->product_name ?? '-',
                        $detail->requested_quantity,
                    ]);
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }

    public function updateStatus(Request $request, $id)
    {
        $inbound = InboundRequest::with('children')->findOrFail($id);

        // Jika tipe adalah batch, update semua anak (Sub-IO) dan induknya
        if ($request->type === 'batch') {
            // Update semua Sub-IO
            $inbound->children()->update(['status' => 'Completed']);
            // Update Induk
            $inbound->update(['status' => 'Completed']);

            $message = "Main IO and all Sub-IOs have been marked as Completed.";
        } else {
            // Update individu (bisa Main IO tanpa split atau Sub-IO itu sendiri)
            $inbound->update(['status' => 'Completed']);

            $message = "Inbound {$inbound->reference_number} marked as Completed.";
        }

        return redirect()->back()->with('success', $message);
    }
}

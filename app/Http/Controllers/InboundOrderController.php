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
        // Handle Reset Filter
        if ($request->has('reset')) {
        session()->forget('inbound_filters');
        return redirect()->route('inbound.index');
    }

    if ($request->isMethod('post')) {
        // Ambil data dari request dan simpan ke session
        session(['inbound_filters' => $request->only([
            'search',
            'inbound_order_no', // Pastikan ini ada di sini
            'client',
            'warehouse',
            'status',
            'date'
        ])]);
    }

    $filters = session('inbound_filters', []);

    // Jalankan Query dengan Filter
    $requests = InboundRequest::with(['details', 'children.details'])
                ->filter($filters)
                ->whereNull('parent_id')
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->paginate(50);

        /**
         * 1. Optimasi Statistik Operasional
         * Kita gunakan with('details') agar virtual attribute (total_items dll) tidak memicu N+1 query.
         * Kita juga membatasi kolom (select) untuk menghemat RAM.
         */
        $allData = InboundRequest::select('id', 'parent_id', 'status')
            ->with('children:id,parent_id,status')
            ->filter($filters)
            ->get();

        // Hitung Statistik berdasarkan logika Split (Child + Parent tanpa child)
        $operationalUnits = $allData->filter(function($item) {
            return $item->parent_id !== null || ($item->parent_id === null && $item->children->count() === 0);
        });

        $stats = (object)[
            'total'        => $operationalUnits->count(),
            'pending'      => $operationalUnits->where('status', 'Pending')->count(),
            'processing'   => $operationalUnits->where('status', 'Processing')->count(),
            'completed'    => $operationalUnits->where('status', 'Completed')->count(),
        ];

        /**
         * 2. Ambil data untuk Tabel Utama
         * Menggunakan Eager Loading bertingkat:
         * - details: Untuk data item milik parent
         * - children.details: Untuk data item milik pecahannya (split)
         */
        $requests = InboundRequest::with([
                'details',
                'children.details'
            ])
            ->filter($filters)
            ->whereNull('parent_id')
            // Optimasi Sort: Pending di atas (menggunakan index status jika ada)
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        $clients = InboundRequest::distinct()->whereNotNull('client_name')->pluck('client_name');
        $warehouses = InboundRequest::distinct()->whereNotNull('warehouse_code')->pluck('warehouse_code');

        return view('inbound.index', compact('requests', 'warehouses', 'clients', 'filters', 'stats'));
    }

    public function scopeFilter($query, array $filters)
    {
        // 1. Search Global (Pencarian Luas)
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                ->orWhere('inbound_order_no', 'LIKE', "%{$search}%")
                ->orWhere('client_name', 'LIKE', "%{$search}%");
            });
        });

        // 2. Filter Spesifik IO Number (Inilah yang membuat filter IO jalan)
        $query->when($filters['inbound_order_no'] ?? null, function ($query, $io) {
            $query->where('inbound_order_no', 'LIKE', "%{$io}%");
        });

        // 3. Filter Client
        $query->when($filters['client'] ?? null, function ($query, $client) {
            $query->where('client_name', $client);
        });

        // 4. Filter Warehouse
        $query->when($filters['warehouse'] ?? null, function ($query, $wh) {
            $query->where('warehouse_code', $wh);
        });

        // 5. Filter Status
        $query->when($filters['status'] ?? null, function ($query, $status) {
            $query->where('status', $status);
        });

        return $query;
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

        // UBAH: Hitung jumlah baris Unique SKU, bukan sum total quantity
        $uniqueSkuCount = $original->details->count();
        $limit = 100;

        // Pengecekan apakah perlu split
        if ($uniqueSkuCount <= $limit) {
            return back()->with('error', "Jumlah Unique SKU ({$uniqueSkuCount}) masih di bawah atau sama dengan limit {$limit}.");
        }

        try {
            DB::beginTransaction();

            $parentId = $original->parent_id ?: $original->id;

            // 2. Gunakan Collection Laravel untuk mempermudah pemecahan (chunk)
            // Kita pecah detail SKU menjadi kelompok-kelompok berisi maksimal 100 baris
            $skuChunks = $original->details->chunk($limit);
            $numberOfIOsNeeded = $skuChunks->count();

            foreach ($skuChunks as $index => $chunk) {
                // Replicate data header dari original
                $childIO = $original->replicate();
                $childIO->parent_id = $parentId;

                // Hitung urutan suffix S01, S02, dst berdasarkan data di database
                $existingChildCount = InboundRequest::where('parent_id', $parentId)->count();
                $childIO->reference_number = $original->reference_number . "-S" . str_pad($existingChildCount + 1, 2, '0', STR_PAD_LEFT);

                $childIO->status = 'Pending';
                $childIO->save();

                // 3. Masukkan baris SKU yang sudah di-chunk ke child IO ini
                foreach ($chunk as $detail) {
                    $childIO->details()->create([
                        'seller_sku' => $detail->seller_sku,
                        'fulfillment_sku' => $detail->fulfillment_sku,
                        'product_name' => $detail->product_name, // Pastikan field ini ada
                        'requested_quantity' => $detail->requested_quantity,
                    ]);
                }
            }

            DB::commit();
            return back()->with('success', "Berhasil memecah {$uniqueSkuCount} SKU menjadi {$numberOfIOsNeeded} dokumen baru (Maks {$limit} SKU per dokumen).");

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

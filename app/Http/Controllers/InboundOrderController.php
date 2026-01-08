<?php

namespace App\Http\Controllers;

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

        $stats = InboundRequest::selectRaw("
            count(*) as total,
            count(case when status = 'Pending' then 1 end) as pending,
            count(case when status = 'Completed' then 1 end) as completed
        ")->first();

        $warehouses = InboundRequest::distinct()->pluck('warehouse_code');

        $requests = InboundRequest::with(['details', 'children.details']) // Memuat data SKU dan Sub-IO sekaligus (Eager Loading)
                ->filter($filters)
                ->whereNull('parent_id') // Hanya ambil dokumen Induk (agar Sub-IO tidak duplikat di list utama)
                ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'asc')
                ->paginate(10);

        return view('inbound.index', compact('requests', 'warehouses', 'filters', 'stats'));
    }

    public function show($id)
    {
        // Mencari InboundRequest beserta detail SKU-nya
        $inbound = InboundRequest::with('details')->findOrFail($id);
        $totalQty = $inbound->details()->sum('requested_quantity');

        return view('inbound.show', compact('inbound','totalQty'));
    }
    public function api(Request $request) {
        // dd($request->skus);
        $validator = Validator::make($request->all(), [
            'comment'        => 'required|string',
            'estimate_time'  => 'required|date_format:Y-m-d\TH:i:s\Z',
            'warehouse_code' => 'required|string',
            'skus'           => 'required'
        ]);

        if ($validator->fails()) {
            $statusCode = 400;
            $responseContent = [
                'success'  => 'FALSE',
                'error_code' => 'Validation failed',
                'error_message'  => $validator->errors()->getMessages()
            ];
            $this->logApi($request, $responseContent, $statusCode);
            return response()->json($responseContent, $statusCode);
        }

        $inboundOrder = InboundRequest::firstOrNew([
            'reference_number' => $request->reference_number
        ]);
        $inboundOrder->warehouse_code        = $request->warehouse_code;
        $inboundOrder->delivery_type         = $request->delivery_type;
        $inboundOrder->seller_warehouse_code = $request->seller_warehouse_code;
        $inboundOrder->estimate_time         = Carbon::parse($request->estimate_time)->toDateTimeString();
        $inboundOrder->comment               = $request->comment;
        $inboundOrder->status                = 'Pending';
        $inboundOrder->save();

        foreach ($request->skus as $sku) {
            $inboundOrderDetail = InboundRequestDetail::firstOrNew([
                'inbound_order_id' => $inboundOrder->id,
                'seller_sku' => $sku['seller_sku'],
                'fulfillment_sku' => $sku['fulfillment_sku'] ?? null,
            ]);
            if ($inboundOrderDetail->requested_quantity == 0) {
                $inboundOrderDetail->requested_quantity = $sku['requested_quantity'];
            } else {
                $inboundOrderDetail->requested_quantity += $sku['requested_quantity'];
            }
            $inboundOrderDetail->save();
        }
        $statusCode = 200;
        $responseContent = [
            'success' => TRUE,
            'data' => $inboundOrder->reference_number
        ];

        $this->logApi($request, $responseContent, $statusCode);
        return response()->json($responseContent, $statusCode);
    }

    private function logApi($request, $response, $status) {
        ApiLog::create([
            'endpoint'    => $request->fullUrl(),
            'method'      => $request->method(),
            'payload'     => $request->all(),
            'response'    => $response,
            'status_code' => $status,
            'ip_address'  => $request->ip(),
        ]);
    }

    public function split($id)
    {
        // 1. Ambil data original beserta detail SKU
        $original = InboundRequest::with('details')->findOrFail($id);
        $limit = 200;
        $totalQty = $original->details->sum('requested_quantity');

        if ($totalQty <= $limit) {
            return back()->with('error', 'Total kuantitas sudah di bawah limit.');
        }

        try {
            DB::beginTransaction();

            $parentId = $original->parent_id ?: $original->id;

            // 2. Kumpulkan semua baris SKU ke dalam satu "pool" besar
            $skuPool = [];
            foreach ($original->details as $detail) {
                $skuPool[] = [
                    'seller_sku' => $detail->seller_sku,
                    'fulfillment_sku' => $detail->fulfillment_sku,
                    'requested_quantity' => (int) $detail->requested_quantity,
                ];
                // Hapus detail lama karena akan didistribusikan ulang
                $detail->delete();
            }

            // 3. Hitung berapa total IO yang dibutuhkan (misal: 500 / 200 = 3 IO)
            $numberOfIOsNeeded = ceil($totalQty / $limit);

            for ($i = 1; $i <= $numberOfIOsNeeded; $i++) {
                // Jika iterasi pertama, gunakan IO original. Jika selanjutnya, buat IO baru (Sub-IO)
                if ($i == 1) {
                    $currentIO = $original;
                } else {
                    $currentIO = $original->replicate();
                    $currentIO->parent_id = $parentId;
                    $childCount = InboundRequest::where('parent_id', $parentId)->count() + 1;
                    $currentIO->reference_number = $original->reference_number . "-S" . str_pad($childCount, 2, '0', STR_PAD_LEFT);
                    $currentIO->save();
                }

                $currentIOCapacity = $limit;

                // 4. Isi IO saat ini dengan SKU dari pool
                foreach ($skuPool as $key => &$sku) {
                    if ($currentIOCapacity <= 0) break;
                    if ($sku['requested_quantity'] <= 0) continue;

                    $take = min($sku['requested_quantity'], $currentIOCapacity);

                    // Buat baris detail baru untuk IO ini
                    $currentIO->details()->create([
                        'seller_sku' => $sku['seller_sku'],
                        'fulfillment_sku' => $sku['fulfillment_sku'],
                        'requested_quantity' => $take,
                    ]);

                    // Kurangi sisa di pool dan sisa kapasitas IO
                    $sku['requested_quantity'] -= $take;
                    $currentIOCapacity -= $take;
                }
            }

            DB::commit();
            return back()->with('success', "Berhasil memecah menjadi " . $numberOfIOsNeeded . " dokumen (Max 200 per IO).");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal split: ' . $e->getMessage());
        }
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
        $instruction = ($vasNeeded === 'Yes') ? $request->vas_instruction : '';

        // LOGIKA BATCH EXPORT (ZIP)
        if ($request->type == 'batch' && $inbound->children->count() > 0) {
            $zipName = "Batch-Export-{$inbound->reference_number}.zip";
            $zip = new ZipArchive;
            $tempFile = tempnam(sys_get_temp_dir(), 'zip');

            if ($zip->open($tempFile, ZipArchive::CREATE) === TRUE) {
                foreach ($inbound->children as $child) {
                    // Buat isi CSV untuk setiap anak
                    $csvContent = fopen('php://temp', 'r+');
                    fputcsv($csvContent, ['Seller SKU', 'Request Quantity', 'Vas Needed', 'Repacking', 'Labeling', 'Bundling', 'No. of items to be bundled', 'Vas Instruction']);

                    foreach ($child->details as $detail) {
                        fputcsv($csvContent, [
                            $detail->seller_sku, $detail->requested_quantity, $vasNeeded,
                            $request->repacking, $request->labeling, $request->bundling,
                            $request->bundle_qty, $instruction
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

        // LOGIKA SINGLE EXPORT (CSV) - Tetap sama seperti sebelumnya
        $filename = "Export-{$inbound->reference_number}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function() use ($inbound, $request, $vasNeeded, $instruction) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Seller SKU', 'Request Quantity', 'Vas Needed', 'Repacking', 'Labeling', 'Bundling', 'No. of items to be bundled', 'Vas Instruction']);
            foreach ($inbound->details as $detail) {
                fputcsv($file, [
                    $detail->seller_sku, $detail->requested_quantity, $vasNeeded,
                    $request->repacking, $request->labeling, $request->bundling,
                    $request->bundle_qty, $instruction
                ]);
            }
            fclose($file);
        }, 200, $headers);
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

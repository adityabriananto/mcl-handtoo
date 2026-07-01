<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInboundUpload;
use App\Models\ApiLog;
use App\Models\ImportStatus;
use App\Models\InboundRequest;
use App\Models\MbMaster;
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
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Collection;

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
            session(['inbound_filters' => $request->only([
                'search', 'inbound_order_no', 'client', 'warehouse', 'status', 'date'
            ])]);
        }

        $filters = session('inbound_filters', []);

        // Cache key untuk stats (invalid saat ada data baru)
        $statsCacheKey = 'admin_inbound_stats_' . md5(serialize($filters));

        // 1. Hitung Statistik — gunakan cache 30 detik
        $stats = cache()->remember($statsCacheKey, 30, function () use ($filters) {
            $statsQuery = InboundRequest::filter($filters);

            $parentStatusCounts = (clone $statsQuery)
                ->whereNull('parent_id')
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $childStatusCounts = (clone $statsQuery)
                ->whereNotNull('parent_id')
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $parentsWithChildren = InboundRequest::filter($filters)
                ->whereNotNull('parent_id')
                ->distinct()
                ->count();

            $totalParents = (clone $statsQuery)->whereNull('parent_id')->count();
            $parentOnlyCount = $totalParents - $parentsWithChildren;
            $totalChildren = array_sum($childStatusCounts);

            return (object)[
                'total'        => $parentOnlyCount + $totalChildren,
                'pending'      => ($parentStatusCounts['Created'] ?? 0) + ($childStatusCounts['Created'] ?? 0),
                'processing'   => ($parentStatusCounts['Inbound in Process'] ?? 0) + ($childStatusCounts['Inbound in Process'] ?? 0),
                'cancelled'    => ($parentStatusCounts['Cancelled by Seller'] ?? 0) + ($childStatusCounts['Cancelled by Seller'] ?? 0),
                'completed'    => ($parentStatusCounts['Completely'] ?? 0) + ($childStatusCounts['Completely'] ?? 0),
                'partially'    => ($parentStatusCounts['Partially'] ?? 0) + ($childStatusCounts['Partially'] ?? 0),
            ];
        });

        // 2. Ambil data untuk Tabel Utama
        $requests = InboundRequest::select([
                'id', 'reference_number', 'fulfillment_order_no', 'client_name',
                'warehouse_code', 'status', 'created_at', 'updated_at', 'estimate_time', 'parent_id', 'comment'
            ])
            ->with([
                'details:id,inbound_order_id,fulfillment_sku,seller_sku,product_name,requested_quantity,received_good',
                'children:id,parent_id,status,reference_number,inbound_order_no,warehouse_code,client_name,comment'
            ])
            ->filter($filters)
            ->whereNull('parent_id')
            ->orderByRaw("CASE WHEN status = 'Created' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        // Cache filter lists (5 menit)
        $clients = cache()->remember('admin_inbound_clients', 300, function () {
            return InboundRequest::distinct()->whereNotNull('client_name')->pluck('client_name');
        });

        $warehouses = cache()->remember('admin_inbound_warehouses', 300, function () {
            return InboundRequest::distinct()->whereNotNull('warehouse_code')->pluck('warehouse_code');
        });

        // Cache Master Data check (5 menit)
        $masterSkus = cache()->remember('admin_master_skus', 300, function () {
            return MbMaster::pluck('fulfillment_sku')->filter()->map(fn($s) => trim($s))->toArray();
        });

        return view('inbound.index', compact('requests', 'warehouses', 'clients', 'filters', 'stats', 'masterSkus'));
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

        // 2. Filter Spesifik IO Number
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

        // 6. Filter Date - gunakan whereBetween untuk performa index yang lebih baik
        $query->when($filters['date'] ?? null, function ($query, $date) {
            $start = \Carbon\Carbon::parse($date)->startOfDay();
            $end = \Carbon\Carbon::parse($date)->endOfDay();
            return $query->whereBetween('created_at', [$start, $end]);
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

    public function opsShow($id)
    {
        // Mencari InboundRequest beserta detail SKU-nya
        $inbound = InboundRequest::with('details')->findOrFail($id);
        $totalQty = $inbound->details()->sum('requested_quantity');

        return view('inbound.ops_show', compact('inbound','totalQty'));
    }

    public function split($id)
    {
        // 1. Ambil data original beserta detail SKU
        $original = InboundRequest::with('details')->findOrFail($id);

        // UBAH: Hitung jumlah baris Unique SKU, bukan sum total quantity
        $uniqueSkuCount = $original->details->count();
        $limit = 200;

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

                $childIO->status = 'Created';
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

        $warehouseMap = config('warehouses.list');

        // Fungsi pembantu untuk memetakan data sesuai header template
        $mapRow = function($model, $sku, $qty) use ($warehouseMap) {
            $warehouseName = $warehouseMap[$model->warehouse_code] ?? $model->warehouse_code;
            if($model->parent_id) {
                $parent = InboundRequest::with(['details', 'children.details'])->findOrFail($model->parent_id);
            } else {
                $parent = null;
            }

            $rawComment = $model->comment;
            $brandCode = trim(explode('_', $rawComment)[0]);
            $mbMaster = MBMaster::where("brand_code",$brandCode)
            ->where("seller_sku", $sku)
            ->first();
            return [
                // 'Delivery Type(required)(must be "Dropoff")' => 'Dropoff',
                // 'Inbound warehouse name(required)' => $warehouseName,
                // 'Reference Order No.' => $parent ? $parent->reference_number : $model->reference_number,
                // 'Estimated Date(required)(yyyy-mm-dd)' => $model->estimate_time ? date('Y-m-d', strtotime($model->estimate_time)) : date('Y-m-d'),
                // 'Estimated Hour(required)(hh)' => $model->estimate_time ? date('H', strtotime($model->estimate_time)) : '00',
                // 'Seller SKU(required)' => $sku,
                // 'Request Quantity(required)' => $qty,
                // 'VAS Needed("Y"/Null)' => '',
                // 'Repacking("Y"/Null)' => '',
                // 'Labeling("Y"/Null)' => '',
                // 'Bundling("Y"/Null)' => '',
                // 'No. of items to be bundled(2~9 integer)' => '',
                // 'VAS instruction(If \'VAS Needed\' is \'Y\', this field is required)(no more than 256 characters)' => ''
                'Fulfillment SKU ID' => $mbMaster->fulfillment_sku ?? "-",
                'Request Quantity' => $qty,
            ];
        };

        // --- LOGIKA BATCH EXPORT (ZIP berisi file XLSX) ---
        if ($request->type == 'batch' && $inbound->children->count() > 0) {
            $zipName = "Batch-Export-{$inbound->reference_number}.zip";
            $zip = new \ZipArchive;
            $tempZip = tempnam(sys_get_temp_dir(), 'zip');

            if ($zip->open($tempZip, \ZipArchive::CREATE) === TRUE) {
                foreach ($inbound->children as $child) {
                    $rows = new Collection();
                    $uniqueSkus = $child->details->groupBy('seller_sku');

                    foreach ($uniqueSkus as $sku => $details) {
                        $rows->push($mapRow($child, $sku, $details->sum('requested_quantity')));
                    }

                    $tempExcel = tempnam(sys_get_temp_dir(), 'xlsx');
                    (new FastExcel($rows))->export($tempExcel);
                    $zip->addFile($tempExcel, "Export-{$child->reference_number}.xlsx");
                }
                $zip->close();
            }
            return response()->download($tempZip, $zipName)->deleteFileAfterSend(true);
        }

        // --- LOGIKA SINGLE EXPORT (XLSX) ---
        $rows = new Collection();
        $uniqueSkus = $inbound->details->groupBy('seller_sku');
        foreach ($uniqueSkus as $sku => $details) {
            $rows->push($mapRow($inbound, $sku, $details->sum('requested_quantity')));
        }

        $filename = "Export-{$inbound->reference_number}.xlsx";
        return (new FastExcel($rows))->download($filename);
    }

    public function exportChildren(InboundRequest $inbound)
    {
        $children = $inbound->children()->with('details')->get();
        if ($children->isEmpty()) return back()->with('error', 'No child documents found.');

        $warehouseMap = config('warehouses.list');

        $rows = new Collection();
        foreach ($children as $child) {
            $uniqueSkus = $child->details->groupBy('seller_sku');
            foreach ($uniqueSkus as $sku => $details) {
                $warehouseName = $warehouseMap[$child->warehouse_code] ?? $child->warehouse_code;
                $rows->push([
                    'Delivery Type(required)(must be "Dropoff")' => 'Dropoff',
                    'Inbound warehouse name(required)' => $warehouseName,
                    'Reference Order No.' => $child->reference_number,
                    'Estimated Date(required)(yyyy-mm-dd)' => $child->estimate_time ? date('Y-m-d', strtotime($child->estimate_time)) : date('Y-m-d'),
                    'Estimated Hour(required)(hh)' => $child->estimate_time ? date('H', strtotime($child->estimate_time)) : '00',
                    'Seller SKU(required)' => $sku,
                    'Request Quantity(required)' => $details->sum('requested_quantity'),
                    'VAS Needed("Y"/Null)' => '',
                    'Repacking("Y"/Null)' => '',
                    'Labeling("Y"/Null)' => '',
                    'Bundling("Y"/Null)' => '',
                    'No. of items to be bundled(2~9 integer)' => '',
                    'VAS instruction(If \'VAS Needed\' is \'Y\', this field is required)(no more than 256 characters)' => ''
                ]);
            }
        }

        return (new FastExcel($rows))->download("Export_Children_{$inbound->reference_number}.xlsx");
    }

    /**
     * Export semua data Inbound yang sesuai dengan filter aktif.
     * Men-flatten parent + children + details ke dalam satu Excel file.
     */
    public function exportAll(Request $request)
    {
        // Ambil filter dari session (sama seperti index())
        $filters = session('inbound_filters', []);

        // Query semua data dengan filter yang sama
        $requests = InboundRequest::with([
                'details:id,inbound_order_id,fulfillment_sku,seller_sku,product_name,requested_quantity,received_good',
                'children:id,parent_id,status,reference_number,inbound_order_no,warehouse_code,client_name,comment,created_at,updated_at,estimate_time'
            ])
            ->filter($filters)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();

        $rows = collect();

        foreach ($requests as $parent) {
            // Jika punya children, export dari children
            if ($parent->children->count() > 0) {
                foreach ($parent->children as $child) {
                    foreach ($child->details as $detail) {
                        $rows->push([
                            'Reference Number' => $parent->reference_number,
                            'Inbound Order No' => $child->inbound_order_no,
                            'Status' => $child->status,
                            'Warehouse' => $child->warehouse_code,
                            'Client' => $child->client_name,
                            'Estimate Time' => $child->estimate_time ? \Carbon\Carbon::parse($child->estimate_time)->format('Y-m-d H:i') : '-',
                            'Created At' => $child->created_at?->format('Y-m-d H:i') ?? '-',
                            'Updated At' => $child->updated_at?->format('Y-m-d H:i') ?? '-',
                            'Fulfillment SKU' => $detail->fulfillment_sku,
                            'Seller SKU' => $detail->seller_sku,
                            'Product Name' => $detail->product_name,
                            'Requested Qty' => $detail->requested_quantity,
                            'Received Good' => $detail->received_good ?? 0,
                        ]);
                    }
                }
            } else {
                // Single IO (tanpa children)
                foreach ($parent->details as $detail) {
                    $rows->push([
                        'Reference Number' => $parent->reference_number,
                        'Inbound Order No' => $parent->inbound_order_no,
                        'Status' => $parent->status,
                        'Warehouse' => $parent->warehouse_code,
                        'Client' => $parent->client_name,
                        'Estimate Time' => $parent->estimate_time ? \Carbon\Carbon::parse($parent->estimate_time)->format('Y-m-d H:i') : '-',
                        'Created At' => $parent->created_at?->format('Y-m-d H:i') ?? '-',
                        'Updated At' => $parent->updated_at?->format('Y-m-d H:i') ?? '-',
                        'Fulfillment SKU' => $detail->fulfillment_sku,
                        'Seller SKU' => $detail->seller_sku,
                        'Product Name' => $detail->product_name,
                        'Requested Qty' => $detail->requested_quantity,
                        'Received Good' => $detail->received_good ?? 0,
                    ]);
                }
            }
        }

        $timestamp = now()->format('Y-m-d_H-i');
        $fileName = "Inbound_Export_{$timestamp}.xlsx";

        return (new FastExcel($rows))->download($fileName);
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $inbound = InboundRequest::with(['children', 'details'])->findOrFail($id);

            if ($request->type === 'batch') {
                // 1. Update Induk & Semua Anak menjadi Completed
                $inbound->update(['status' => 'Completely']);
                $inbound->children()->update(['status' => 'Completely']);

                // 2. Update Quantity untuk Semua (Induk & Anak) secara masif
                $allIds = $inbound->children->pluck('id')->push($inbound->id);

                DB::table('inbound_order_details')
                    ->whereIn('inbound_order_id', $allIds)
                    ->update([
                        'received_good' => DB::raw('requested_quantity'),
                        'updated_at' => now()
                    ]);

                $message = "Main IO and all Sub-IOs marked as Completed with quantities synced.";
            } else {
                // 1. Update Status IO/Sub-IO yang dipilih
                $inbound->update(['status' => 'Completely']);

                // 2. Sync Quantity: received_good = requested_quantity pada item yang diklik
                foreach ($inbound->details as $detail) {
                    $detail->update(['received_good' => $detail->requested_quantity]);
                }

                // 3. Jika ini adalah Child, sinkronkan STATUS dan DATA QUANTITY ke Parent
                if ($inbound->parent_id) {
                    // Sinkronkan Status (Logic yang sudah kita buat sebelumnya)
                    $this->syncParentStatus($inbound->parent_id);

                    // Sinkronkan Data Quantity dari Child ke Parent
                    $this->syncParentDataFromChildren($inbound->parent_id);
                }

                $message = "Inbound {$inbound->reference_number} marked as Completed.";
            }

            DB::commit();
            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    private function syncParentDataFromChildren($parentId)
    {
        $parent = InboundRequest::with('details')->find($parentId);
        $childrenIds = InboundRequest::where('parent_id', $parentId)->pluck('id');

        foreach ($parent->details as $parentDetail) {
            // Hitung total received_good dari semua child untuk SKU yang sama
            $totalReceived = DB::table('inbound_order_details')
                ->whereIn('inbound_order_id', $childrenIds)
                ->where('seller_sku', $parentDetail->seller_sku)
                ->sum('received_good');

            $parentDetail->update([
                'received_good' => $totalReceived
            ]);
        }
    }

    public function opsIndex(Request $request)
    {
        // 1. Handle Reset Filter khusus untuk Ops
        if ($request->has('reset')) {
            session()->forget('ops_inbound_filters');
            return redirect()->route('ops.inbound.index');
        }

        // 2. Simpan Filter ke Session (Gunakan key berbeda agar tidak tabrakan dengan Admin)
        if ($request->isMethod('post') || $request->hasAny(['search', 'status'])) {
            session(['ops_inbound_filters' => $request->only([
                'search',
                'inbound_order_no',
                'client',
                'warehouse',
                'status',
                'date'
            ])]);
        }

        $filters = session('ops_inbound_filters', []);

        /**
         * 3. LOGIKA FILTER OTOMATIS
         * Ops publik tidak boleh melihat data yang sudah 'Completed'
         * kecuali mereka sengaja memfilternya (atau kita kunci sama sekali).
         */
        $query = InboundRequest::select([
                'id', 'reference_number', 'fulfillment_order_no', 'client_name',
                'warehouse_code', 'status', 'created_at', 'updated_at', 'estimate_time', 'parent_id'
            ])
            ->with(['details:id,inbound_order_id,fulfillment_sku,seller_sku,product_name,requested_quantity,received_good', 'children:id,parent_id,status'])
            ->filter($filters)
            ->whereNull('parent_id');

        // 4. Hitung Statistik — gunakan cache 30 detik
        $statsCacheKey = 'ops_inbound_stats_' . md5(serialize($filters));

        $stats = cache()->remember($statsCacheKey, 30, function () use ($filters) {
            $statsQuery = InboundRequest::filter($filters);

            // Single query: hitung parent dan child status sekaligus
            $allCounts = (clone $statsQuery)
                ->selectRaw('
                    parent_id,
                    status,
                    count(*) as total
                ')
                ->groupBy('parent_id', 'status')
                ->get();

            $parentStatusCounts = [];
            $childStatusCounts = [];
            $parentsWithChildren = 0;

            foreach ($allCounts as $row) {
                if ($row->parent_id === null) {
                    $parentStatusCounts[$row->status] = ($parentStatusCounts[$row->status] ?? 0) + $row->total;
                } else {
                    $childStatusCounts[$row->status] = ($childStatusCounts[$row->status] ?? 0) + $row->total;
                    $parentsWithChildren++;
                }
            }

            $totalParents = (clone $statsQuery)->whereNull('parent_id')->count();
            $parentOnlyCount = $totalParents - $parentsWithChildren;
            $totalChildren = array_sum($childStatusCounts);

            return (object)[
                'total'        => $parentOnlyCount + $totalChildren,
                'pending'      => ($parentStatusCounts['Created'] ?? 0) + ($childStatusCounts['Created'] ?? 0),
                'processing'   => ($parentStatusCounts['Inbound in Process'] ?? 0) + ($childStatusCounts['Inbound in Process'] ?? 0),
                'cancelled'    => ($parentStatusCounts['Cancelled by Seller'] ?? 0) + ($childStatusCounts['Cancelled by Seller'] ?? 0),
                'completed'    => ($parentStatusCounts['Completely'] ?? 0) + ($childStatusCounts['Completely'] ?? 0),
                'partially'    => ($parentStatusCounts['Partially'] ?? 0) + ($childStatusCounts['Partially'] ?? 0),
            ];
        });

        // 5. Final Query untuk Table
        $requests = $query->orderByRaw("CASE WHEN status = 'Created' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        // Cache clients & warehouses untuk performa
        $clients = cache()->remember('ops_clients_list', 300, function () {
            return InboundRequest::distinct()->whereNotNull('client_name')->pluck('client_name');
        });
        $warehouses = cache()->remember('ops_warehouses_list', 300, function () {
            return InboundRequest::distinct()->whereNotNull('warehouse_code')->pluck('warehouse_code');
        });

        // Data Freshness Indicator: Last Updated time
        $lastUpdated = cache()->remember('ops_last_updated', 60, function () {
            return InboundRequest::max('updated_at');
        });

        // Gunakan view yang sama, logika blade @auth/@guest akan menangani perbedaan tombol
        return view('inbound.ops_index', compact('requests', 'warehouses', 'clients', 'filters', 'stats', 'lastUpdated'));
    }

    public function uploadActualQuantity(Request $request)
    {
        $request->validate([
            'actual_file' => 'required|mimes:xlsx,xls,csv|max:20480', // Support up to 20MB
        ]);

        try {
            $file = $request->file('actual_file');
            $fileName = time() . '_actual_' . $file->getClientOriginalName();

            // Simpan ke folder temp di storage/app/uploads
            $path = $file->storeAs('uploads', $fileName, 'local');
            $fullPath = Storage::disk('local')->path($path);

            // Count rows for feedback message
            $rowCount = 0;
            try {
                (new FastExcel)->import($fullPath, function ($row) use (&$rowCount) {
                    $rowCount++;
                    return $row;
                });
            } catch (\Exception $e) {
                $rowCount = 0;
            }

            // Simpan log ke import_statuses
            $importLog = ImportStatus::create([
                'filename' => $file->getClientOriginalName(),
                'total_rows' => $rowCount,
                'processed_rows' => 0,
                'status' => 'processing',
            ]);

            // Dispatch Job ke queue 'io-result-upload'
            \App\Jobs\ProcessInboundActualUpload::dispatch($fullPath, $importLog->id)->onQueue('io-result-upload');

            return back()->with('upload_success', [
                'message' => "WMS Actual Upload Successful! {$rowCount} rows are being processed in the background.",
                'filename' => $file->getClientOriginalName(),
                'rows' => $rowCount,
            ]);

        } catch (\Exception $e) {
            return back()->with('upload_error', [
                'message' => 'Upload Failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Export Master Correlation: PO -> Items flattened list.
     * Respects current session filters.
     */
    public function masterExport(Request $request)
    {
        $filters = session('ops_inbound_filters', []);

        // Query dengan filter yang sama seperti opsIndex
        $query = InboundRequest::with(['details', 'children.details'])
            ->filter($filters)
            ->whereNull('parent_id');

        $inbounds = $query->get();

        $rows = collect();

        foreach ($inbounds as $inbound) {
            // Jika punya children (split orders), flatten dari children
            if ($inbound->children->count() > 0) {
                foreach ($inbound->children as $child) {
                    foreach ($child->details as $detail) {
                        $rows->push([
                            'Reference Number (PO)' => $inbound->reference_number,
                            'Inbound Order (IO)' => $child->fulfillment_order_no,
                            'Status' => $child->status,
                            'Fulfillment SKU' => $detail->fulfillment_sku,
                            'Seller SKU' => $detail->seller_sku,
                            'Item Name' => $detail->product_name,
                            'Requested Qty' => $detail->requested_quantity,
                            'Received Qty' => $detail->received_good ?? 0,
                        ]);
                    }
                }
            } else {
                // Single order, flatten dari details langsung
                foreach ($inbound->details as $detail) {
                    $rows->push([
                        'Reference Number (PO)' => $inbound->reference_number,
                        'Inbound Order (IO)' => $inbound->fulfillment_order_no,
                        'Status' => $inbound->status,
                        'Fulfillment SKU' => $detail->fulfillment_sku,
                        'Seller SKU' => $detail->seller_sku,
                        'Item Name' => $detail->item_name,
                        'Requested Qty' => $detail->requested_quantity,
                        'Received Qty' => $detail->received_good ?? 0,
                    ]);
                }
            }
        }

        $fileName = 'master_correlation_' . now('Asia/Jakarta')->format('Ymd_His') . '.xlsx';

        return (new FastExcel($rows))->download($fileName);
    }

    public function resetStatus($id)
    {
        try {
            DB::beginTransaction();

            $inbound = InboundRequest::with('children')->findOrFail($id);

            // 1. Tentukan ID mana saja yang perlu di-reset
            // Jika Parent, sertakan semua Child-nya. Jika Child, hanya dirinya sendiri.
            $idsToReset = collect([$inbound->id]);
            if ($inbound->children->count() > 0) {
                $idsToReset = $idsToReset->merge($inbound->children->pluck('id'));
            }

            // 2. Reset Status ke Processing untuk semua ID terkait
            InboundRequest::whereIn('id', $idsToReset)->update(['status' => 'Inbound in Process']);

            // 3. Reset Quantity (received_good) menjadi 0 untuk semua detail terkait
            DB::table('inbound_order_details')
                ->whereIn('inbound_order_id', $idsToReset)
                ->update(['received_good' => 0, 'updated_at' => now()]);

            // 4. Jika yang di-reset adalah Child individu, update status Parent-nya
            if ($inbound->parent_id) {
                $this->syncParentStatus($inbound->parent_id);
                // Update quantity parent berdasarkan sisa child yang mungkin masih completed
                $this->syncParentDataFromChildren($inbound->parent_id);
            }

            DB::commit();
            return back()->with('success', 'Status dan quantity berhasil di-reset.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal reset: ' . $e->getMessage());
        }
    }

    private function syncParentStatus($parentId)
    {
        $parent = InboundRequest::find($parentId);
        if (!$parent) return;

        $statusCounts = InboundRequest::where('parent_id', $parentId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalChildren = array_sum($statusCounts);
        $completedCount = $statusCounts['Completed'] ?? 0;

        $hasProgress = ($statusCounts['Inbound in Process'] ?? 0) > 0 ||
                    ($statusCounts['Partially'] ?? 0) > 0 ||
                    ($completedCount > 0);

        // Logic Penentuan Status
        if ($completedCount === $totalChildren && $totalChildren > 0) {
            $newStatus = 'Completely';
        } elseif ($hasProgress) {
            // Jika ada satu saja child yang sudah Completed atau sedang Processing
            $newStatus = 'Partially';
        } else {
            $newStatus = 'Inbound in Process';
        }

        $parent->update(['status' => $newStatus]);
    }

    /**
     * Method untuk melakukan pembatalan Inbound Order (Parent atau Child)
     */
    public function cancelIO($id)
    {
        try {
            DB::beginTransaction();

            // 1. Ambil data Inbound beserta ID Child-nya saja agar ringan
            $inbound = InboundRequest::select('id', 'parent_id')->with('children:id,parent_id')->findOrFail($id);

            // 2. Kumpulkan semua ID yang terlibat
            $idsToCancel = collect([$inbound->id]);

            if ($inbound->children->isNotEmpty()) {
                // Jika yang di-klik adalah Parent, kumpulkan semua ID Child-nya
                $idsToCancel = $idsToCancel->merge($inbound->children->pluck('id'));
            }

            // 3. Update Status secara Massal (Bulk)
            InboundRequest::whereIn('id', $idsToCancel)->update([
                'status' => 'Cancelled by Lazada',
                'updated_at' => now()
            ]);

            // 4. Reset Quantity di tabel detail secara Massal
            DB::table('inbound_order_details')
                ->whereIn('inbound_order_id', $idsToCancel)
                ->update([
                    'received_good' => 0,
                    'updated_at' => now()
                ]);

            // 5. Sinkronisasi Final
            // Jika yang di-cancel adalah Child (punya parent_id), sync Parent-nya.
            // Jika yang di-cancel adalah Parent, jalankan sync untuk dirinya sendiri (untuk update qty detail).
            $targetSyncId = $inbound->parent_id ?: $inbound->id;
            $this->syncParentDataAfterCancel($targetSyncId);

            DB::commit();
            return back()->with('success', 'Inbound Order dan semua Child terkait berhasil dibatalkan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Method khusus sinkronisasi setelah cancel untuk memastikan
     * kuantitas dan status parent tetap akurat.
     */
    private function syncParentDataAfterCancel($parentId)
    {
        $parent = InboundRequest::with(['children.details', 'details'])->find($parentId);
        if (!$parent) return;

        $hasChildren = $parent->children->isNotEmpty();

        // --- STEP 1: Update Quantity Parent (Hanya jika punya anak) ---
        if ($hasChildren) {
            foreach ($parent->details as $parentDetail) {
                $totalReceived = InboundRequestDetail::whereIn('inbound_order_id', $parent->children->pluck('id'))
                    ->where('fulfillment_sku', $parentDetail->fulfillment_sku)
                    ->sum('received_good');

                $parentDetail->update(['received_good' => $totalReceived]);
            }
        }

        // --- STEP 2: Update Status Parent (Hirarki) ---
        // Jika Single IO (tidak punya anak) dan statusnya sudah 'Cancelled by Lazada', jangan ditimpa lagi.
        if (!$hasChildren) {
            // Jika status saat ini bukan Cancelled, baru kita tentukan statusnya (biasanya default)
            // Tapi dalam konteks cancelIO, statusnya sudah diupdate di method utama.
            return;
        }

        $childrenStatus = $parent->children()->pluck('status')->unique()->toArray();

        if (in_array('Inbound in Process', $childrenStatus)) {
            $newStatus = 'Inbound in Process';
        } elseif (in_array('Partially', $childrenStatus)) {
            $newStatus = 'Partially';
        } else {
            $totalChildren  = $parent->children()->count();
            $totalCompleted = $parent->children()->where('status', 'Completely')->count();
            $totalCancelled = $parent->children()->where('status', 'Cancelled by Lazada')->count();

            // Logika untuk Parent yang semua anaknya sudah selesai/batal
            if ($totalChildren > 0 && ($totalCompleted + $totalCancelled) === $totalChildren) {
                $newStatus = ($totalCompleted > 0) ? 'Completely' : 'Cancelled by Lazada';
            } else {
                $newStatus = 'Inbound in Process';
            }
        }

        $parent->update(['status' => $newStatus]);
    }

    public function markAsArrived($id)
    {
        try {
            DB::beginTransaction();

            $inbound = InboundRequest::findOrFail($id);

            // Validasi Status
            if (in_array($inbound->status, ['Completely', 'Partially', 'Cancelled by Seller', 'Cancelled by Lazada'])) {
                return redirect()->back()->with('error', 'Cannot mark as arrived for this status.');
            }

            // 1. Update Parent
            $inbound->update(['is_arrived' => 1]);

            // 2. Update Semua Child (Jika ada)
            $inbound->children()->update(['is_arrived' => 1]);

            DB::commit();
            return redirect()->back()->with('success', "Inbound and all Sub-IOs marked as Arrived.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Display import log (WMS Actual Upload) with filters.
     */
    public function importLog(Request $request)
    {
        $query = ImportStatus::query();

        // Filter by filename
        if ($request->filled('filename')) {
            $query->where('filename', 'like', '%' . $request->input('filename') . '%');
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by created_at date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('inbound.import-log', compact('logs'));
    }
}

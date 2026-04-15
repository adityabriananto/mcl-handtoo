<?php

namespace App\Http\Controllers;

use App\Http\Resources\InboundResource;
use App\Http\Resources\InboundResourceDetail;
use App\Models\ApiLog;
use App\Models\ClientApi;
use App\Models\InboundRequest;
use App\Models\InboundRequestDetail;
use App\Jobs\ProcessInboundSkusJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class InboundOrderApiController extends Controller
{
    //
    public function createInboundOrder(Request $request) {
        // 1. Dekode 'skus' jika dikirim dalam bentuk string JSON
        if (is_string($request->skus)) {
            $decodedSkus = json_decode($request->skus, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['skus' => $decodedSkus]);
            }
        }

        $validator = Validator::make($request->all(), [
            'comment'                   => 'required|string',
            'reference_number'          => 'required|string',
            'estimate_time'             => 'required|date_format:Y-m-d\TH:i:s\Z',
            'warehouse_code'            => 'required|string',
            'skus'                      => 'required|array',
            'skus.*.seller_sku'         => 'required|string',
            'skus.*.requested_quantity' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return $this->buildApiResponse(false, 'VALIDATION_FAILED', $validator->errors()->first(), 400, $request, 'CreateInboundOrder');
        }

        // 2. Ambil token dari Header atau dari Body (berdasarkan log Anda ada di body juga)
        // 3. Cache Client API (Gunakan Cache untuk menaikkan RPS)
        $client = \Cache::remember("client_api_{$request->app_key}", 3600, function() use ($request) {
            return ClientApi::where('app_key', $request['app_key'])->first();
        });
        // $client = ClientApi::where('app_key', $request['app_key'])->first();

        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, 'CreateInboundOrder');
        }

        try {
            // Gunakan Transaction agar data Parent & Detail konsisten
            return \DB::transaction(function () use ($request, $client) {

                // // 4. Update atau Create Inbound Parent
                // $inboundOrder = InboundRequest::firstOrNew(
                //     [
                //         'client_name'      => $client->client_name,
                //         'reference_number' => $request->reference_number
                //     ],
                //     [
                //         'warehouse_code'        => $request->warehouse_code,
                //         'delivery_type'         => $request->delivery_type,
                //         'seller_warehouse_code' => $request->seller_warehouse_code,
                //         'estimate_time'         => Carbon::parse($request->estimate_time)->toDateTimeString(),
                //         'comment'               => $request->comment,
                //         'status'                => 'Created',
                //     ]
                // );

                // 1. Ambil data lama atau buat instance baru di memori
                $inboundOrder = InboundRequest::firstOrNew([
                    'client_name'      => $client->client_name,
                    'reference_number' => $request->reference_number
                ]);

                // 2. Isi dengan data baru dari API
                $inboundOrder->warehouse_code        = $request->warehouse_code;
                $inboundOrder->delivery_type         = $request->delivery_type;
                $inboundOrder->seller_warehouse_code = $request->seller_warehouse_code;
                $inboundOrder->estimate_time         = Carbon::parse($request->estimate_time)->toDateTimeString();
                $inboundOrder->comment               = $request->comment;

                // Jika ini data baru, set status awal
                if (!$inboundOrder->exists) {
                    $inboundOrder->status = 'Created';
                }

                // 3. LOGIKA PENGECEKAN (Pilih perubahan)
                // Cek apakah ada kolom tertentu yang berubah sebelum di-save
                if ($inboundOrder->isDirty('status')) {
                    // Lakukan sesuatu jika status berubah...
                }

                if ($inboundOrder->isDirty()) {
                    // Hanya simpan ke database jika memang ada perubahan (Dirty)
                    $inboundOrder->save();
                }

                if($inboundOrder->status == 'Created') {
                    ProcessInboundSkusJob::dispatch($inboundOrder->id, $request->skus)->onQueue('create-inbound-order');
                }

                return $this->buildApiResponse(true, null, $inboundOrder->reference_number, 200, $request, 'CreateInboundOrder');
            });

        } catch (\Exception $e) {
            \Log::error("Error Create Inbound: " . $e->getMessage());
            return $this->buildApiResponse(false, 'SERVER_ERROR', 'Terjadi kesalahan internal.', 500, $request, 'CreateInboundOrder');
        }
    }


    public function getInboundOrders(Request $request) {
        // 1. Validasi Client berdasarkan Token
        $client = ClientApi::where('app_key', $request['app_key'])->first();

        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, 'GetInboundOrders');
        }

        // 2. Query Inbound dengan Filter:
        // - Hanya milik Client terkait
        // - Hanya Main IO (Asumsi: kolom is_main = 1 atau parent_id null)
        // - Hanya ambil kolom yang dibutuhkan
        $inbound = InboundRequest::where('client_name', $client->client_name)
            ->where(function($query) {
                // Hanya mengambil dokumen utama (Main IO)
                $query->whereNull('parent_id');
            })
            // Menambahkan 'comment' (brand_os) ke dalam seleksi kolom
            ->select('reference_number', 'status', 'comment as brand_os')
            ->get();

        // 3. Response handling
        if ($inbound->isEmpty()) {
            return $this->buildApiResponse(true, null, [], 200, $request, 'GetInboundOrders');
        } else {
            return $this->buildApiResponse(true, null, $inbound, 200, $request, 'GetInboundOrders');
        }
    }

    public function getInboundOrderDetail(Request $request)
    {
        $client = ClientApi::where('app_key', $request['app_key'])->first();
        $type = 'GetInboundOrderDetails';

        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, $type);
        }

        /** * 1. Query Ringan untuk Validasi & Metadata
         * Kita hanya ambil kolom yang diperlukan untuk mengecek eksistensi dan waktu update terakhir.
         */
        $baseData = InboundRequest::select('id', 'reference_number', 'updated_at', 'comment')
                    ->where('client_name', $client->client_name)
                    ->where('reference_number', $request['inbound_order_no'])
                    ->first();

        if (!$baseData) {
            return $this->buildApiResponse(false, 'Data Not Found', 'Inbound Order Number not found.', 400, $request, $type);
        }

        /** * 2. Strategi Cache Key Dinamis
         * Key mengandung timestamp 'updated_at'.
         * Jika status di database berubah -> updated_at berubah -> Key berubah -> Cache lama dianggap hangus (miss).
         */
        $cacheKey = "inbound_v1_{$baseData->reference_number}_{$baseData->updated_at->timestamp}";

        // 3. Ambil data dari cache, hangus otomatis dalam 1 hari (60 * 60 * 24 detik)
        $finalData = Cache::remember($cacheKey, now()->addDay(), function () use ($baseData) {
            // Query Berat (Eager Loading) hanya jalan jika cache tidak ada/hangus
            $inbound = InboundRequest::with(['details', 'children', 'parent'])->find($baseData->id);

            // Simpan dalam bentuk array agar cache lebih ringan dan cepat di-load
            return (new InboundResourceDetail($inbound))->toArray(request());
        });

        return $this->buildApiResponse(true, null, $finalData, 200, $request, $type);
    }

    public function cancel(Request $request)
    {
        $type = 'CancelInboundOrder';

        // 1. Cek Client API
        $client = ClientApi::where('app_key', $request['app_key'])->first();
        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, $type);
        }

        // 2. Validasi Input
        $validator = Validator::make($request->all(), [
            'inbound_order_no' => 'required|exists:inbound_orders,reference_number'
        ]);

        if ($validator->fails()) {
            return $this->buildApiResponse(false, 'VALIDATION_ERROR', $validator->errors()->first(), 400, $request, $type);
        }

        $inbound = InboundRequest::where('reference_number', $request->inbound_order_no)->first();

        // 3. Validasi State (Status)
        if ($inbound->status === 'Cancelled by Seller') {
            return $this->buildApiResponse(false, 'ALREADY_CANCELLED', 'Inbound is already cancelled.', 400, $request, $type);
        }

        if ($inbound->status === 'Completely' || $inbound->status === 'Partially') {
            return $this->buildApiResponse(false, 'FORBIDDEN', 'Cannot cancel a completed inbound.', 400, $request, $type);
        }

        if ($inbound->is_arrived) {
            return $this->buildApiResponse(false, 'Package Arrived', 'Package already arrived at wh and on process inbounded.', 400, $request, $type);
        }

        try {
            \DB::transaction(function () use ($inbound) {
                // Jika Parent: Cancel bapak dan anak-anak yang belum completed
                if (!$inbound->parent_id && $inbound->children->count() > 0) {
                    $inbound->update(['status' => 'Cancelled by Seller']);
                    $inbound->children()
                    ->where('status', '!=', 'Completely')
                    ->where('status', '!=', 'Partially')
                    ->update(['status' => 'Cancelled by Seller']);
                }
                // Jika Child atau Single: Cancel diri sendiri dan sync bapaknya
                else {
                    $inbound->update(['status' => 'Cancelled by Seller']);
                    if ($inbound->parent_id) {
                        $this->updateParentStatusAfterChildCancel($inbound->parent_id);
                    }
                }
            });

            // 4. Response Berhasil
            $dataResponse = [
                "reference_number" => $inbound->reference_number,
                "status"           => "Cancelled by Seller",
                "updated_at"       => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z')
            ];

            return $this->buildApiResponse(true, null, $dataResponse, 200, $request, $type);

        } catch (\Exception $e) {
            // 5. Response Error Server
            return $this->buildApiResponse(false, 'SERVER_ERROR', $e->getMessage(), 500, $request, $type);
        }
    }

    /**
     * Logika sinkronisasi status Parent setelah salah satu Child di-cancel
     */
    private function updateParentStatusAfterChildCancel($parentId)
    {
        // Ambil data parent beserta status semua anaknya
        $parent = InboundRequest::with('children')->find($parentId);
        if (!$parent) return;

        $childrenStatuses = $parent->children()->pluck('status')->toArray();
        $totalChildren    = count($childrenStatuses);

        // Hitung distribusi status anak-anaknya
        $cancelledCount = count(array_filter($childrenStatuses, fn($s) => $s === 'Cancelled by Seller'));
        $completedCount = count(array_filter($childrenStatuses, fn($s) => $s === 'Completely'));

        /**
         * LOGIKA KEPUTUSAN STATUS PARENT:
         */

        // 1. Jika SEMUA anak sudah Cancelled, maka Parent otomatis Cancelled.
        if ($cancelledCount === $totalChildren) {
            $parent->update(['status' => 'Cancelled by Seller']);
        }
        // 2. Jika ada anak yang sudah Completed (berhasil masuk) tapi ada juga yang Cancelled,
        //    maka Parent dianggap "Partial Completed" karena tidak semua barang masuk.
        elseif ($completedCount > 0) {
            $parent->update(['status' => 'Partially']);
        }
        // 3. Jika ada yang Cancelled tapi sisanya masih "Processing" (belum di-apa-apakan),
        //    maka Parent tetap menjadi "Partial Completed" sebagai tanda ada pembatalan di tengah jalan.
        elseif ($cancelledCount > 0 && $cancelledCount < $totalChildren) {
            $parent->update(['status' => 'Partially']);
        }
    }

    protected function buildApiResponse($success, $message, $data, $status, $request, $type)
    {
        if($type == "CreateInboundOrder") {
            $response = [
                "success"           => $success ? True : False,
                "code"             => $success ? "0" : (string) $status,
                "inbound_order_no" => $data, // Ini akan berisi hasil dari InboundResourceDetail
                "request_id"       => (string) \Str::uuid()
            ];
        } else if ($type == "CancelInboundOrder") {
            $response = [
                "success"           => $success ? True : False,
                "code"             => $success ? "0" : (string) $status,
                "request_id"       => (string) \Str::uuid()
            ];
        }
        else {
            $response = [
                "success"     => $success ? True : False,
                "code"       => $success ? "0" : (string) $status,
                "data"       => $data, // Ini akan berisi hasil dari InboundResourceDetail
                "request_id" => (string) \Str::uuid()
            ];
        }

        // Jika terjadi error, kita bisa selipkan message di dalam data atau level atas
        if (!$success) {
            $response = [
                "error_message"    => $data,
                "error_code"       => $message,
                "success"          => $success ? True : False,
                "code"             => $success ? "0" : (string) $status,
                "request_id"       => (string) \Str::uuid()
            ];
        }

        // Simpan ke log tabel api_logs
        // $this->logApi($request, $response, $status, $type);

        $clientIp = request()->ip();
        $orderNo = $request['inbound_order_no'] ?? $request['reference_number'] ?? null;

        $isSpamIp     = ($clientIp === '159.138.103.145');
        $isSpamPrefix = ($orderNo && str_starts_with((string)$orderNo, 'IO0'));

        // Simpan ke log HANYA jika bukan spam
        if (!$isSpamIp && !$isSpamPrefix) {
            $this->logApi($request, $response, $status, $type);
        }

        return response()->json($response, $status);
    }

    private function logApi($request, $response, $status, $type) {
        $client = ClientApi::where('app_key',$request['app_key'])->first();
        // \Log::info($request->fullUrl());
        $fullUrl = explode("?",$request->fullUrl());
        if (empty($client)) {
            ApiLog::create([
                'client_name' => "-",
                'api_type'    => $type,
                'endpoint'    => $fullUrl[0],
                'method'      => $request->method(),
                'payload'     => $request->all(),
                'response'    => $response,
                'status_code' => $status,
                'ip_address'  => $request->ip(),
            ]);
        } else {
            ApiLog::create([
                'client_name' => $client->client_name,
                'api_type'    => $type,
                'endpoint'    => $fullUrl[0],
                'method'      => $request->method(),
                'payload'     => $request->all(),
                'response'    => $response,
                'status_code' => $status,
                'ip_address'  => $request->ip(),
            ]);
        }
    }
}

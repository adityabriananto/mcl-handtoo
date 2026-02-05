<?php

namespace App\Http\Controllers;

use App\Http\Resources\InboundResource;
use App\Http\Resources\InboundResourceDetail;
use App\Models\ApiLog;
use App\Models\ClientApi;
use App\Models\InboundRequest;
use App\Models\InboundRequestDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            'comment'        => 'required|string',
            'reference_number' => 'required|string',
            'estimate_time'  => 'required', // Format fleksibel mengikuti Carbon
            'warehouse_code' => 'required|string',
            'skus'           => 'required|array',
            'skus.*.seller_sku' => 'required|string',
            'skus.*.requested_quantity' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return $this->buildApiResponse(false, 'VALIDATION_FAILED', $validator->errors()->first(), 400, $request, 'CreateInboundOrder');
        }

        // 2. Ambil token dari Header atau dari Body (berdasarkan log Anda ada di body juga)
        $client = ClientApi::where('app_key', $request['app_key'])->first();

        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, 'CreateInboundOrder');
        }

        // 3. Simpan atau Update Inbound Request
        $inboundOrder = InboundRequest::firstOrNew([
            'client_name'      => $client->client_name,
            'reference_number' => $request->reference_number
        ]);

        $inboundOrder->warehouse_code        = $request->warehouse_code;
        $inboundOrder->delivery_type         = $request->delivery_type;
        $inboundOrder->seller_warehouse_code = $request->seller_warehouse_code;
        $inboundOrder->estimate_time         = Carbon::parse($request->estimate_time)->toDateTimeString();
        $inboundOrder->comment               = $request->comment;
        $inboundOrder->status                = 'Pending';
        $inboundOrder->save();

        // 4. Proses SKU
        foreach ($request->skus as $sku) {
            $inboundOrderDetail = InboundRequestDetail::firstOrNew([
                'inbound_order_id' => $inboundOrder->id,
                'seller_sku'       => $sku['seller_sku'],
                'fulfillment_sku'  => $sku['fulfillment_sku'] ?? null,
            ]);

            // Akumulasi quantity jika item yang sama dikirim dua kali
            $inboundOrderDetail->requested_quantity += (int) $sku['requested_quantity'];
            $inboundOrderDetail->save();
        }

        return $this->buildApiResponse(true, null, $inboundOrder->reference_number, 200, $request, 'CreateInboundOrder');
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
            $statusCode = 200;
            $responseContent = [
                'success' => true,
                'message' => 'No references found',
                'data'    => []
            ];
            return $this->buildApiResponse(true, null, [], 200, $request, 'GetInboundOrders');
        } else {
            $statusCode = 200;
            $responseContent = [
                'success' => true,
                'message' => 'Success fetch inbound references',
                'data'    => $inbound
            ];
            return $this->buildApiResponse(true, null, $inbound, 200, $request, 'GetInboundOrders');
        }
    }

    public function getInboundOrderDetail(Request $request)
    {
        $client = ClientApi::where('app_key', $request['app_key'])->first();

        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, 'GetInboundOrderDetails');
        }

        // Pastikan memanggil first() di akhir dan muat relasi yang dibutuhkan Resource
        $query = InboundRequest::with(['details', 'children', 'parent'])
                    ->where('client_name', $client->client_name)
                    ->where('reference_number', $request['inbound_order_no']);

        if ($request['brand_os']) {
            $query->where('comment', $request['brand_os']);
        }

        $inbound = $query->first(); // Ambil data pertama atau null

        if (!$inbound) {
            // $statusCode = 404;
            // $responseContent = [
            //     'success' => false,
            //     'message' => 'Reference number not found.'
            // ];
            // $this->logApi($request, $responseContent, $statusCode, 'GetInboundOrderDetails');
            // return response()->json($responseContent, $statusCode);
            return $this->buildApiResponse(false, 'Data Not Found', 'Inbound Order Number not found.', 400, $request, 'GetInboundOrderDetails');

        }

        // $statusCode = 200;
        // $responseContent = [
        //     'success' => true,
        //     'data' => new InboundResourceDetail($inbound)
        // ];

        // $this->logApi($request, $responseContent, $statusCode, 'GetInboundOrder');
        // return response()->json($responseContent, $statusCode);
        return $this->buildApiResponse(true, null, new InboundResourceDetail($inbound), 200, $request, 'GetInboundOrderDetails');
    }

    private function buildApiResponse($success, $errorCode, $dataOrMessage, $status, $request, $type) {
        $responseContent = [
            'success' => $success,
            'code'    => $status,
            'body'    => $success ? $dataOrMessage : null,
            'error'   => !$success ? [
                'error'    => $errorCode,
                'error_message' => $dataOrMessage
            ] : null
        ];

        // Simpan Log
        $this->logApi($request, $responseContent, $status, $type);

        return response()->json($responseContent, $status)
                        ->header('Content-Type', 'application/json');
    }

    public function cancel(Request $request)
    {
        $type = 'CancelInboundOrder';

        $client = ClientApi::where('app_key', $request['app_key'])->first();

        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, 'GetInboundOrderDetails');
        }

        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'inbound_order_no' => 'required|exists:inbound_orders,reference_number'
        ]);

        if ($validator->fails()) {
            $response = ['code' => '1', 'message' => $validator->errors()->first()];
            $this->logApi($request, $response, 400, $type); // Log Error Validasi
            return response()->json($response, 400);
        }

        $inbound = InboundRequest::where('reference_number', $request->inbound_order_no)->first();

        // 2. Cek Status (Jika sudah Cancelled)
        if ($inbound->status === 'Cancelled') {
            $response = ['code' => '1', 'message' => 'Inbound is already cancelled.'];
            $this->logApi($request, $response, 400, $type);
            return response()->json($response, 400);
        }

        // 3. Cek Status (Jika sudah Completed)
        if ($inbound->status === 'Completed') {
            $response = ['code' => '1', 'message' => 'Cannot cancel a completed inbound.'];
            $this->logApi($request, $response, 400, $type);
            return response()->json($response, 400);
        }

        try {
            \DB::transaction(function () use ($inbound) {
                if (!$inbound->parent_id && $inbound->children->count() > 0) {
                    $inbound->update(['status' => 'Cancelled']);
                    $inbound->children()->where('status', '!=', 'Completed')->update(['status' => 'Cancelled']);
                } else {
                    $inbound->update(['status' => 'Cancelled']);
                    if ($inbound->parent_id) {
                        $this->updateParentStatusAfterChildCancel($inbound->parent_id);
                    }
                }
            });

            // 4. Log Berhasil
            $response = [
                "code" => "0",
                "data" => [
                    "reference_number" => $inbound->reference_number,
                    "status"           => "Cancelled",
                    "updated_at"       => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z')
                ],
                "request_id" => (string) \Illuminate\Support\Str::uuid()
            ];

            $this->logApi($request, $response, 200, $type);
            return response()->json($response, 200);

        } catch (\Exception $e) {
            // 5. Log Error Server
            $response = ['code' => '1', 'message' => 'Internal Server Error: ' . $e->getMessage()];
            $this->logApi($request, $response, 500, $type);
            return response()->json($response, 500);
        }
    }

    /**
     * Logika sinkronisasi status Parent setelah salah satu Child di-cancel
     */
    private function updateParentStatusAfterChildCancel($parentId)
    {
        $parent = InboundRequest::with('children')->find($parentId);
        if (!$parent) return;

        $childrenStatus = $parent->children()->pluck('status')->toArray();
        $totalChildren = count($childrenStatus);
        $cancelledCount = count(array_filter($childrenStatus, fn($s) => $s === 'Cancelled'));
        $completedCount = count(array_filter($childrenStatus, fn($s) => $s === 'Completed'));

        // Sesuai permintaan: Jika salah satu child di-cancel,
        // status parent menjadi 'Partial Completed' (asumsi ada child lain yang masih jalan/selesai)
        if ($cancelledCount > 0 && $cancelledCount < $totalChildren) {
            $parent->update(['status' => 'Partial Completed']);
        }
        // Jika ternyata semua child akhirnya menjadi cancelled
        elseif ($cancelledCount === $totalChildren) {
            $parent->update(['status' => 'Cancelled']);
        }
        // Jika semua sisa child sudah Completed
        elseif ($completedCount + $cancelledCount === $totalChildren) {
            $parent->update(['status' => 'Cancelled']);
        }
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

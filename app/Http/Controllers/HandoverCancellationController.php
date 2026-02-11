<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\CancellationRequest;
use App\Models\ClientApi;
use App\Models\HandoverBatch;
use Illuminate\Http\Request;
use App\Models\HandoverDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HandoverCancellationController extends Controller
{
    public function cancel(Request $request)
    {
        // Menggunakan biz_time atau request_id (untuk simulasi request_id)
        $requestId = now()->format('ymdHis') . bin2hex(random_bytes(4));

        $validator = Validator::make($request->all(), [
            'cancel_reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->buildApiResponse($request, false, 'Missing cancel_reason', 'INVALID_PARAMS', $requestId);
        }

        $client = ClientApi::where('app_key', $request['app_key'])->first();
        if (empty($client)) {
            return $this->buildApiResponse($request, false, 'Missing app_key / app_key not registered', 'AUTH_ERROR', $requestId);
        }

        DB::beginTransaction();
        try {
            $awb = $request['cancel_reason'];
            $cancelRequest = CancellationRequest::where('tracking_number', $awb)->first();
            $handoverWaybill = HandoverDetail::where('airwaybill', $awb)->first();

            // 1. Kondisi: Duplicate Request
            if ($cancelRequest) {
                return $this->buildApiResponse($request, false, 'Duplicate Tracking Number', 'DUPLICATE_ERROR', $requestId);
            }

            // 2. Kondisi: Sudah terdaftar di Handover
            if ($handoverWaybill) {
                $handover = HandoverBatch::where('handover_id', $handoverWaybill->handover_id)->first();

                if ($handover && $handover->status === 'completed') {
                    CancellationRequest::create([
                        'tracking_number' => $awb,
                        'status' => 'Rejected',
                        'reason' => 'Package already handed over to 3PL'
                    ]);
                    DB::commit();
                    return $this->buildApiResponse($request, false, 'Package already handed over to 3PL', 'HANDOVER_TO_3PL', $requestId);
                } else {
                    CancellationRequest::create([
                        'tracking_number' => $awb,
                        'status' => 'Approved'
                    ]);

                    $handoverWaybill->is_cancelled = true;
                    $handoverWaybill->save();
                    DB::commit();
                    return $this->buildApiResponse($request, true, 'cancelled', '0', $requestId);
                }
            }

            // 3. Kondisi: Baru (Belum discan sama sekali)
            CancellationRequest::create([
                'tracking_number' => $awb,
                'status' => 'Approved'
            ]);

            DB::commit();
            return $this->buildApiResponse($request, true, 'cancelled', '0', $requestId);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->buildApiResponse($request, false, $e->getMessage(), 'INTERNAL_SERVER_ERROR', $requestId);
        }
    }

    /**
     * Helper untuk memformat response sesuai permintaan khusus Anda
     */
    // private function formatCustomResponse($success, $status, $bizTime, $httpCode, $data = null)
    // {
    //     // Mengumpulkan items jika ada data handover detail
    //     $items = [];
    //     if ($data && $data->details) {
    //         // Grouping by SKU untuk menggabungkan quantity sesuai instruksi
    //         $groupedItems = $data->details->groupBy('fulfillment_sku_id');
    //         foreach ($groupedItems as $skuId => $details) {
    //             $items[] = [
    //                 "quantity" => $details->sum('quantity'), // Aggregation qty
    //                 "fulfillment_sku_id" => (string)$skuId,
    //                 "owner_id" => $data->owner_id ?? "2214728602664",
    //                 "unit_price" => $details->first()->unit_price ?? "0",
    //                 "platform_item_id" => ($data->sales_order_number ?? "Bagus") . "-" . $skuId . "-0",
    //                 "seller_id" => $data->seller_id ?? "400599399044",
    //                 "status" => $status
    //             ];
    //         }
    //     }

    //     $response = [
    //         "headers" => [
    //             "content-type" => "application/json;charset=UTF-8"
    //         ],
    //         "body" => [
    //             "sales_order_number" => $data->sales_order_number ?? "BagusCancel",
    //             "platform_order_id" => $data->platform_order_id ?? "BagusCancel",
    //             "owner_id" => $data->owner_id ?? "2214728602664",
    //             "platform_name" => $data->platform_name ?? "TEST_ID",
    //             "biz_time" => $bizTime,
    //             "items" => $items,
    //             "seller_id" => $data->seller_id ?? "400599399044"
    //         ]
    //     ];

    //     return response()->json($response, $httpCode);
    // }

    private function buildApiResponse($request, $isSuccess, $message, $errorCode, $requestId, $type = 'CANCEL_API')
    {
        $responseData = [
            'success'       => $isSuccess ? "TRUE" : "FALSE",
            'code'          => $isSuccess ? "0" : $errorCode,
            'error_message' => $isSuccess ? "" : $message,
            'error_code'    => $isSuccess ? "0" : $errorCode,
            'request_id'    => $requestId,
        ];

        $statusCode = $isSuccess ? 200 : 400;

        // Tambahkan pencatatan log sebelum return
        $this->logApi($request, $responseData, $statusCode, $type);

        return response()->json($responseData, $statusCode);
    }

    // Helper untuk log agar code lebih bersih
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

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
        // 1. Inisialisasi biz_time tepat saat ini dengan format UTC (Z)
        $bizTime = now()->utc()->format('Y-m-d\TH:i:s.000\Z');

        $validator = Validator::make($request->all(), [
            'cancel_reason' => 'required|string|max:255', // Menggunakan cancel_reason sebagai tracking number (awb)
        ]);

        if ($validator->fails()) {
            return $this->formatCustomResponse(false, 'Validation failed', $bizTime, 400);
        }

        $client = ClientApi::where('app_key', $request['app_key'])->first();
        if (empty($client)) {
            return $this->formatCustomResponse(false, 'UNAUTHORIZED', $bizTime, 401);
        }

        DB::beginTransaction();
        try {
            $awb = $request['cancel_reason'];
            $cancelRequest = CancellationRequest::where('tracking_number', $awb)->first();
            // Mengambil detail handover beserta item/details untuk kebutuhan response
            $handoverWaybill = HandoverDetail::with('details')->where('airwaybill', $awb)->first();

            // 1. Kondisi: Sudah terdaftar di Handover
            if (!$cancelRequest && $handoverWaybill) {
                $handover = HandoverBatch::where('handover_id', $handoverWaybill->handover_id)->first();

                if ($handover && $handover->status === 'completed') {
                    CancellationRequest::create([
                        'tracking_number' => $awb,
                        'status' => 'Rejected',
                        'reason' => 'Package already handed over to 3PL'
                    ]);
                    DB::commit();
                    return $this->formatCustomResponse(false, 'handover_to_3pl', $bizTime, 400, $handoverWaybill);
                } else {
                    CancellationRequest::create([
                        'tracking_number' => $awb,
                        'status' => 'Approved'
                    ]);

                    $handoverWaybill->is_cancelled = true;
                    $handoverWaybill->save();
                    DB::commit();
                    return $this->formatCustomResponse(true, 'cancelled', $bizTime, 200, $handoverWaybill);
                }
            }

            // 2. Kondisi: Duplicate Request
            if ($cancelRequest) {
                return $this->formatCustomResponse(false, 'Duplicate Tracking Number', $bizTime, 400);
            }

            // 3. Kondisi: Baru (Belum discan sama sekali)
            if (!$cancelRequest && !$handoverWaybill) {
                CancellationRequest::create([
                    'tracking_number' => $awb,
                    'status' => 'Approved'
                ]);
                DB::commit();
                return $this->formatCustomResponse(true, 'cancelled', $bizTime, 200);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->formatCustomResponse(false, 'Error: ' . $e->getMessage(), $bizTime, 500);
        }
    }

    /**
     * Helper untuk memformat response sesuai permintaan khusus Anda
     */
    private function formatCustomResponse($success, $status, $bizTime, $httpCode, $data = null)
    {
        // Mengumpulkan items jika ada data handover detail
        $items = [];
        if ($data && $data->details) {
            // Grouping by SKU untuk menggabungkan quantity sesuai instruksi
            $groupedItems = $data->details->groupBy('fulfillment_sku_id');
            foreach ($groupedItems as $skuId => $details) {
                $items[] = [
                    "quantity" => $details->sum('quantity'), // Aggregation qty
                    "fulfillment_sku_id" => (string)$skuId,
                    "owner_id" => $data->owner_id ?? "2214728602664",
                    "unit_price" => $details->first()->unit_price ?? "0",
                    "platform_item_id" => ($data->sales_order_number ?? "Bagus") . "-" . $skuId . "-0",
                    "seller_id" => $data->seller_id ?? "400599399044",
                    "status" => $status
                ];
            }
        }

        $response = [
            "headers" => [
                "content-type" => "application/json;charset=UTF-8"
            ],
            "body" => [
                "sales_order_number" => $data->sales_order_number ?? "BagusCancel",
                "platform_order_id" => $data->platform_order_id ?? "BagusCancel",
                "owner_id" => $data->owner_id ?? "2214728602664",
                "platform_name" => $data->platform_name ?? "TEST_ID",
                "biz_time" => $bizTime,
                "items" => $items,
                "seller_id" => $data->seller_id ?? "400599399044"
            ]
        ];

        return response()->json($response, $httpCode);
    }

    private function buildApiResponse($success, $errorCode, $dataOrMessage, $status, $request, $type) {
        $responseContent = [
            'success' => $success,
            'code'    => $status,
            'data'    => $success ? $dataOrMessage : null,
            'error'   => !$success ? [
                'type'    => $errorCode,
                'message' => $dataOrMessage
            ] : null
        ];

        // Simpan Log
        $this->logApi($request, $responseContent, $status, $type);

        return response()->json($responseContent, $status)
                        ->header('Content-Type', 'application/json');
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

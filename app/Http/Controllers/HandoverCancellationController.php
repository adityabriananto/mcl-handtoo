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
        $responseContent = [];
        $statusCode = 200;

        $validator = Validator::make($request->all(), [
            'cancel_reason'   => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            // $statusCode = 400;
            // $responseContent = [
            //     'success'  => FALSE,
            //     'error_code' => 'Validation failed',
            //     'error_message'  => $validator->errors()->getMessages()
            // ];
            return $this->buildApiResponse(false,'Validation failed', $validator->errors()->getMessages(), 400, $request, 'CancelFulfillmentOrder');
        }

        $client = ClientApi::where('app_key',$request['app_key'])->first();
        if (empty($client)) {
            return $this->buildApiResponse(false, 'UNAUTHORIZED', 'app_key not found', 401, $request, 'CancelFulfillmentOrder');
        }

        DB::beginTransaction();
        try {
            $awb = $request['cancel_reason'];
            $cancelRequest = CancellationRequest::where('tracking_number', $awb)->first();
            $handoverWaybill = HandoverDetail::where('airwaybill', $awb)->first();

            // Cari parent batch-nya

            if (
                !$cancelRequest &&
                $handoverWaybill
            ) {
                $handover = HandoverBatch::where('handover_id', $handoverWaybill->handover_id)->first();
                if($handover->status === 'completed') {
                    CancellationRequest::create([
                        'tracking_number' => $awb,
                        'status' => 'Rejected',
                        'reason' => 'Package already handed over to 3PL'
                    ]);
                    return $this->buildApiResponse(false,'Cancel Failed', 'Package already handed over to 3PL', 400, $request, 'CancelFulfillmentOrder');
                } else {
                    CancellationRequest::create([
                        'tracking_number' => $awb,
                        'status' => 'Approved'
                    ]);
                    // HAPUS DARI DATABASE
                    // Ini akan membuat pengecekan di method scan() mendeteksi bahwa data sudah tidak ada
                    $handoverWaybill->is_cancelled = true;
                    $handoverWaybill->save();
                    return $this->buildApiResponse(true, null, 'Tracking number '.$awb.' Cancelled', 200, $request, 'CancelFulfillmentOrder');
                }
            }

            if($cancelRequest) {
                // $statusCode = 400;
                // $responseContent = [
                //     'success'  => FALSE,
                //     'error_code' => 'Cancel Failed',
                //     'error_message'  => 'Duplicate Tracking Number'
                // ];
                return $this->buildApiResponse(false,'Cancel Failed', 'Duplicate Tracking Number', 400, $request, 'CancelFulfillmentOrder');
            }

            if (
                !$cancelRequest &&
                !$handoverWaybill
            ) {
                CancellationRequest::create([
                    'tracking_number' => $awb,
                    'status' => 'Approved'
                ]);
                // $statusCode = 200;
                // $responseContent = [
                //     'success' => TRUE,
                //     'message' => 'Tracking number '.$awb.' Cancelled'
                // ];
                return $this->buildApiResponse(true, null, 'Tracking number '.$awb.' Cancelled', 200, $request, 'CancelFulfillmentOrder');
            }

            // if($handover && $handover->status != 'completed') {
            //     // HAPUS DARI DATABASE
            //     // Ini akan membuat pengecekan di method scan() mendeteksi bahwa data sudah tidak ada
            //     $handoverWaybill->delete();

            //     $statusCode = 200;
            //     $responseContent = [
            //         'success' => TRUE,
            //         'message' => 'Tracking number '.$awb.' Cancelled'
            //     ];
            // } else {
            //     $statusCode = 400;
            //     $responseContent = [
            //         'success'  => FALSE,
            //         'error_code' => 'Cancel Failed',
            //         'error_message'  => 'Package already handed over to 3PL or Batch not found'
            //     ];
            // }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // $statusCode = 400;
            // $responseContent = [
            //     'success'  => FALSE,
            //     'error_message' => 'Error: ' . $e->getMessage()
            // ];
            return $this->buildApiResponse(false,'Error', 'Error: ' . $e->getMessage(), 200, $request, 'CancelFulfillmentOrder');
        }

        // $this->logApi($request, $responseContent, $statusCode,'HandoverCancellation');
        // return response()->json($responseContent, $statusCode);
        // return $this->buildApiResponse($success, $errorCode, $dataOrMessage, $status, $request, $type);
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

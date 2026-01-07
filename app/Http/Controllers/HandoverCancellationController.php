<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\CancellationRequest;
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
            $statusCode = 400;
            $responseContent = [
                'success'  => 'FALSE',
                'error_code' => 'Validation failed',
                'error_message'  => $validator->errors()->getMessages()
            ];
            $this->logApi($request, $responseContent, $statusCode);
            return response()->json($responseContent, $statusCode);
        }

        DB::beginTransaction();
        try {
            $awb = $request->cancel_reason;
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
                    $statusCode = 400;
                    $responseContent = [
                        'success'  => 'FALSE',
                        'error_code' => 'Cancel Failed',
                        'error_message'  => 'Package already handed over to 3PL'
                    ];
                } else {
                    CancellationRequest::create([
                        'tracking_number' => $awb,
                        'status' => 'Approved'
                    ]);
                    $statusCode = 200;
                    $responseContent = [
                        'success' => 'TRUE',
                        'message' => 'Tracking number '.$awb.' Cancelled'
                    ];
                    // HAPUS DARI DATABASE
                    // Ini akan membuat pengecekan di method scan() mendeteksi bahwa data sudah tidak ada
                    $handoverWaybill->is_cancelled = true;
                    $handoverWaybill->save();
                }
            }

            if($cancelRequest) {
                $statusCode = 400;
                $responseContent = [
                    'success'  => 'FALSE',
                    'error_code' => 'Cancel Failed',
                    'error_message'  => 'Duplicate Tracking Number'
                ];
            }

            if (
                !$cancelRequest &&
                !$handoverWaybill
            ) {
                CancellationRequest::create([
                    'tracking_number' => $awb,
                    'cancel_reason' => $request->cancel_reason,
                    'status' => 'Approved'
                ]);
                $statusCode = 200;
                $responseContent = [
                    'success' => 'TRUE',
                    'message' => 'Tracking number '.$awb.' Cancelled'
                ];
            }

            // if($handover && $handover->status != 'completed') {
            //     // HAPUS DARI DATABASE
            //     // Ini akan membuat pengecekan di method scan() mendeteksi bahwa data sudah tidak ada
            //     $handoverWaybill->delete();

            //     $statusCode = 200;
            //     $responseContent = [
            //         'success' => 'TRUE',
            //         'message' => 'Tracking number '.$awb.' Cancelled'
            //     ];
            // } else {
            //     $statusCode = 400;
            //     $responseContent = [
            //         'success'  => 'FALSE',
            //         'error_code' => 'Cancel Failed',
            //         'error_message'  => 'Package already handed over to 3PL or Batch not found'
            //     ];
            // }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $statusCode = 400;
            $responseContent = [
                'success'  => 'FALSE',
                'error_message' => 'Error: ' . $e->getMessage()
            ];
        }

        $this->logApi($request, $responseContent, $statusCode);
        return response()->json($responseContent, $statusCode);
    }

    // Helper untuk log agar code lebih bersih
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
}

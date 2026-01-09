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
                'success'  => FALSE,
                'error_code' => 'Validation failed',
                'error_message'  => $validator->errors()->getMessages()
            ];
            $this->logApi($request, $responseContent, $statusCode, 'CreateInboundOrder');
            return response()->json($responseContent, $statusCode);
        }

        $client = ClientApi::where('access_token',$request->header()['authorization'])->first();

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

        $this->logApi($request, $responseContent, $statusCode, 'CreateInboundOrder');
        return response()->json($responseContent, $statusCode);
    }


    public function getInboundOrders(Request $request) {
        // 1. Validasi Client berdasarkan Token
        $authHeader = $request->header('authorization');
        $client = ClientApi::where('access_token', $authHeader)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 401);
        }

        // 2. Query Inbound dengan Filter:
        // - Hanya milik Client terkait
        // - Hanya Main IO (Asumsi: kolom is_main = 1 atau parent_id null)
        // - Hanya ambil kolom yang dibutuhkan
        $inbound = InboundRequest::where('client_name', $client->client_name)
                    ->where(function($query) {
                        // Sesuaikan dengan struktur database Anda untuk membedakan Main IO
                        $query->WhereNull('parent_id');
                    })
                    ->select('reference_number', 'status') // Hanya ambil 2 kolom ini
                    ->get();

        // 3. Response handling
        if ($inbound->isEmpty()) {
            $statusCode = 200;
            $responseContent = [
                'success' => true,
                'message' => 'No references found',
                'data'    => []
            ];
        } else {
            $statusCode = 200;
            $responseContent = [
                'success' => true,
                'message' => 'Success fetch inbound references',
                'data'    => $inbound
            ];
        }

        // 4. Log API
        $this->logApi($request, $responseContent, $statusCode, 'GetInboundOrders');

        return response()->json($responseContent, $statusCode);
    }

    public function getInboundOrderDetail(Request $request)
    {
        $client = ClientApi::where('access_token',$request->header()['authorization'])->first();
        $inbound = InboundRequest::with('details')
                    ->where('client_name', $client->client_name)
                    ->where('reference_number', $request->reference_number)
                    ->first();

        if (!$inbound) {
             $statusCode = 404;
            $responseContent = [
                'success'  => FALSE,
                'message' => 'Inbound Order tidak ditemukan.'
            ];
            $this->logApi($request, $responseContent, $statusCode, 'GetInboundOrderDetails');
            return response()->json($responseContent, $statusCode);
        }


        $statusCode = 200;
        $responseContent = [
            'success' => TRUE,
            'data' => new InboundResourceDetail($inbound)
        ];

        $this->logApi($request, $responseContent, $statusCode, 'GetInboundOrder');
        return response()->json($responseContent, $statusCode);
    }

    private function logApi($request, $response, $status, $type) {
        $client = ClientApi::where('access_token',$request->header()['authorization'])->first();
        ApiLog::create([
            'client_name' => $client->client_name,
            'api_type'    => $type,
            'endpoint'    => $request->fullUrl(),
            'method'      => $request->method(),
            'payload'     => $request->all(),
            'response'    => $response,
            'status_code' => $status,
            'ip_address'  => $request->ip(),
        ]);
    }
}

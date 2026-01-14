<?php

namespace App\Jobs;

use App\Models\InboundRequest;
use App\Models\InboundRequestDetail;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class ProcessInboundUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle()
    {
        if (!file_exists($this->filePath)) return;

        try {
            $spreadsheet = IOFactory::load($this->filePath);
            $rows = $spreadsheet->getActiveSheet()->toArray();

            foreach ($rows as $index => $rawData) {
                if ($index === 0) continue; // Skip header

                // --- 1. PEMBERSIHAN GLOBAL ---
                // Mengubah string kosong atau spasi menjadi NULL agar tidak merusak DB
                $data = array_map(function($value) {
                    $trimmed = trim($value);
                    return ($trimmed === "" || $trimmed === null) ? null : $trimmed;
                }, $rawData);

                $ioNum  = $data[0];   // IO Number
                $refNum = $data[15];  // Reference Order No.
                $sku    = $data[7];   // Seller SKU

                if (empty($refNum)) continue;

                // --- 2. UPDATE HEADER (InboundRequest) ---
                $inbound = InboundRequest::where('reference_number', $refNum)->first();

                if ($inbound) {
                    // Hanya update kolom yang benar-benar ada di file
                    // dd($data);
                    $headerData = array_filter([
                        'inbound_order_no'       => $ioNum,
                        'fulfillment_order_no'   => $data[1],
                        'shop_name'              => $data[2],
                        'created_time'           => $this->formatDate($data[3]),
                        'estimated_inbound_time' => $this->formatDate($data[4]),
                        'inbound_warehouse'      => $data[9],
                        'delivery_type'          => $data[11],
                        'status'                 => 'Processing',
                        'io_status'              => $data[13]
                    ], fn($value) => !is_null($value)); // Proteksi: Jangan timpa data lama dengan NULL

                    $inbound->update($headerData);

                    // --- 3. UPDATE DETAIL (InboundOrderDetail) ---
                    if (!empty($sku)) {
                        $detailData = [
                            'fulfillment_sku'    => $data[6],
                            'product_name'       => $data[8],
                            'sku_status'         => $data[12],
                            'requested_quantity' => (int)($data[16] ?? 0),
                            'received_good'      => (int)($data[17] ?? 0),
                            'received_damaged'   => (int)($data[26] ?? 0),
                            'received_expired'   => (int)($data[27] ?? 0),
                            'cogs'               => $data[28],
                            'cogs_currency'      => $data[29],
                            'seller_comment'     => $data[30],
                            'temperature'        => $data[38],
                            'product_type'       => $data[39],
                        ];

                        // Gunakan updateOrCreate agar SKU unik per Reference Number
                        InboundRequestDetail::updateOrCreate(
                            [
                                'inbound_order_id' => $inbound->id,
                                'seller_sku'       => $sku
                            ],
                            $detailData
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Upload Error: " . $e->getMessage());
        } finally {
            if (file_exists($this->filePath)) unlink($this->filePath);
        }
    }

    private function formatDate($value) {
        if (empty($value)) return null;
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}

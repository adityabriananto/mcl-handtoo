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

            $cachedParents = [];
            $parentIdsToSync = [];

            foreach ($rows as $index => $rawData) {
                if ($index === 0) continue;

                $data = array_map(fn($v) => (trim($v) === "") ? null : trim($v), $rawData);
                $ioNum  = $data[0];
                $refNum = $data[15];
                $sku    = $data[7];

                if (empty($refNum)) continue;

                if (!isset($cachedParents[$refNum])) {
                    $cachedParents[$refNum] = InboundRequest::with(['children.details'])
                        ->where('reference_number', $refNum)
                        ->first();
                }

                $parentInbound = $cachedParents[$refNum];

                if ($parentInbound) {
                    $targetInbound = $parentInbound;
                    $isSplit = false;

                    // 1. Logika Cari Target (Cek apakah SKU ada di Child)
                    if ($parentInbound->children->isNotEmpty()) {
                        $foundInChild = $parentInbound->children->first(function ($child) use ($sku) {
                            return $child->details->contains('seller_sku', $sku);
                        });

                        if ($foundInChild) {
                            $targetInbound = $foundInChild;
                            $isSplit = true; // Tandai bahwa ini adalah data child
                        }
                    }

                    // 2. Update Header Target (Parent atau Child)
                    $headerData = array_filter([
                        'inbound_order_no'       => $ioNum,
                        'fulfillment_order_no'   => $data[1],
                        'shop_name'              => $data[2],
                        'created_time'           => $this->formatDate($data[3]),
                        'estimated_inbound_time' => $this->formatDate($data[4]),
                        'inbound_warehouse'      => $data[9],
                        'delivery_type'          => $data[11],
                        'status'                 => 'Inbound in Process',
                        'io_status'              => $data[13]
                    ], fn($value) => !is_null($value));

                    $targetInbound->update($headerData);

                    // 3. Update Detail SKU
                    if (!empty($sku)) {
                        $detailData = [
                            'fulfillment_sku'    => $data[6],
                            'product_name'       => $data[8],
                            'sku_status'         => $data[12],
                            'received_good'      => (int)($data[17] ?? 0),
                            'received_damaged'   => (int)($data[26] ?? 0),
                            'received_expired'   => (int)($data[27] ?? 0),
                            'cogs'               => $data[28],
                            'cogs_currency'      => $data[29],
                            'seller_comment'     => $data[30],
                            'temperature'        => $data[38],
                            'product_type'       => $data[39],
                        ];

                        // UPDATE TARGET (Bisa Child atau Parent)
                        InboundRequestDetail::updateOrCreate(
                            ['inbound_order_id' => $targetInbound->id, 'seller_sku' => $sku],
                            $detailData
                        );

                        // SINKRONISASI KE PARENT (Hanya jika target tadi adalah Child)
                        if ($isSplit) {
                            // Update informasi yang sama di level Parent agar data sinkron
                            // Note: Header parent biasanya tidak diupdate dengan data child
                            // agar tetap bersih, tapi detail SKU wajib sinkron untuk reporting.
                            InboundRequestDetail::updateOrCreate(
                                ['inbound_order_id' => $parentInbound->id, 'seller_sku' => $sku],
                                $detailData
                            );

                            // Opsional: Jika ingin header Parent juga terupdate informasi dasar
                            $parentInbound->update($headerData);
                        }
                    }

                    $parentIdsToSync[] = $parentInbound->id;
                }
            }

            // --- SYNC STATUS AKHIR ---
            foreach (array_unique($parentIdsToSync) as $parentId) {
                $p = InboundRequest::with('children')->find($parentId);
                if ($p) {
                    $p->update(['status' => 'Inbound in Process']);
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

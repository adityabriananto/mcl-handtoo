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

            // Koleksi untuk menampung data parent agar tidak query berulang
            $cachedParents = [];
            $parentIdsToSync = [];

            foreach ($rows as $index => $rawData) {
                if ($index === 0) continue;

                $data = array_map(fn($v) => (trim($v) === "") ? null : trim($v), $rawData);
                $ioNum  = $data[0];
                $refNum = $data[15];
                $sku    = $data[7];

                if (empty($refNum)) continue;

                // Optimasi: Cek di cache memori dulu sebelum query ke DB
                if (!isset($cachedParents[$refNum])) {
                    $cachedParents[$refNum] = InboundRequest::with(['children.details'])
                        ->where('reference_number', $refNum)
                        ->first();
                }

                $parentInbound = $cachedParents[$refNum];

                if ($parentInbound) {
                    $targetInbound = $parentInbound;

                    // Logika Target (Jika IO Split)
                    if ($parentInbound->children->isNotEmpty()) {
                        $foundInChild = $parentInbound->children->first(function ($child) use ($sku) {
                            return $child->details->contains('seller_sku', $sku);
                        });
                        if ($foundInChild) $targetInbound = $foundInChild;
                    }

                    // Update Header
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

                    // Update Detail
                    if (!empty($sku)) {
                        InboundRequestDetail::updateOrCreate(
                            ['inbound_order_id' => $targetInbound->id, 'seller_sku' => $sku],
                            [
                                'fulfillment_sku'    => $data[6],
                                'product_name'       => $data[8],
                                'requested_quantity' => (int)($data[16] ?? 0),
                                'received_good'      => (int)($data[17] ?? 0),
                                'received_damaged'   => (int)($data[26] ?? 0),
                                'received_expired'   => (int)($data[27] ?? 0),
                                'cogs'               => $data[28],
                                'cogs_currency'      => $data[29],
                                'seller_comment'     => $data[30],
                                'temperature'        => $data[38],
                                'product_type'       => $data[39],
                            ]
                        );
                    }

                    // Simpan ID untuk sync status nanti
                    $parentIdsToSync[] = $parentInbound->id;
                }
            }

            // --- SYNC STATUS SEMUA PARENT YANG TERLIBAT ---
            foreach (array_unique($parentIdsToSync) as $parentId) {
                $p = InboundRequest::with('children')->find($parentId);
                if ($p && $p->children->isNotEmpty()) {
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

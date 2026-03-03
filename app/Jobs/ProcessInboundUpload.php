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
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
                if ($index === 0) continue; // Skip header

                // 1. Pembersihan Global
                $data = array_map(fn($v) => (trim($v) === "") ? null : trim($v), $rawData);

                $ioNum  = $data[0];   // Inbound Order No
                $refNum = $data[15];  // Reference Order No
                $sku    = $data[7];   // Seller SKU (Pastikan index benar sesuai file Anda)

                if (empty($refNum)) continue;

                // Optimasi Query: Cache parent agar tidak query berulang dalam loop
                if (!isset($cachedParents[$refNum])) {
                    $cachedParents[$refNum] = InboundRequest::with(['children.details'])
                        ->where('reference_number', $refNum)
                        ->first();
                }

                $parentInbound = $cachedParents[$refNum];

                if ($parentInbound) {
                    $targetInbound = $parentInbound;
                    $isSplit = false;

                    // --- LOGIKA PENCARIAN TARGET (Parent vs Child) ---
                    if ($parentInbound->children->isNotEmpty()) {
                        $foundInChild = $parentInbound->children->first(function ($child) use ($sku) {
                            return $child->details->contains('seller_sku', $sku);
                        });

                        if ($foundInChild) {
                            $targetInbound = $foundInChild;
                            $isSplit = true;
                        }
                    }

                    // --- 2. PREPARASI DATA HEADER ---
                    // Header umum (tanpa nomor order) untuk sinkronisasi Parent
                    $baseHeaderData = array_filter([
                        'shop_name'              => $data[2],
                        'created_time'           => $this->formatDate($data[3]),
                        'estimated_inbound_time' => $this->formatDate($data[4]),
                        'inbound_warehouse'      => $data[9],
                        'delivery_type'          => $data[11],
                        'status'                 => 'Inbound in Process',
                        'io_status'              => $data[13]
                    ], fn($value) => !is_null($value));

                    // Header lengkap (dengan nomor order) untuk Target (Child atau Single Parent)
                    $fullHeaderData = array_merge($baseHeaderData, [
                        'inbound_order_no'     => $ioNum,
                        'fulfillment_order_no' => $data[1],
                    ]);

                    // Update Target Inbound
                    $targetInbound->update($fullHeaderData);

                    // --- 3. UPDATE DETAIL SKU ---
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

                        // Update Target Detail (Child/Single)
                        InboundRequestDetail::updateOrCreate(
                            ['inbound_order_id' => $targetInbound->id, 'seller_sku' => $sku],
                            $detailData
                        );

                        // Sinkronisasi ke Parent jika target adalah Child
                        if ($isSplit) {
                            // Update Detail di level Parent (Wajib agar angka report sinkron)
                            InboundRequestDetail::updateOrCreate(
                                ['inbound_order_id' => $parentInbound->id, 'seller_sku' => $sku],
                                $detailData
                            );

                            // Update Header Parent: TANPA inbound_order_no dan fulfillment_order_no
                            $parentInbound->update($baseHeaderData);
                        }
                    }

                    $parentIdsToSync[] = $parentInbound->id;
                }
            }

            // --- 4. SYNC STATUS AKHIR ---
            foreach (array_unique($parentIdsToSync) as $parentId) {
                $p = InboundRequest::with('children')->find($parentId);
                if ($p) {
                    $p->update(['status' => 'Inbound in Process']);
                }
            }

        } catch (\Exception $e) {
            Log::error("Upload Inbound Error: " . $e->getMessage());
        } finally {
            // Hapus file temporary setelah selesai
            if (file_exists($this->filePath)) unlink($this->filePath);
        }
    }

    private function formatDate($value)
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}

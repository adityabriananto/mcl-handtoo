<?php

namespace App\Jobs;

use App\Models\InboundRequest;
use App\Models\InboundRequestDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class ProcessInboundActualUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle()
    {
        // 1. Ambil semua data dari Excel ke dalam Collection
        $allRows = (new FastExcel)->import($this->filePath);

        // 2. Pecah menjadi chunk per 100 data
        $allRows->chunk(100)->each(function ($chunk) {
            DB::transaction(function () use ($chunk) {
                $inboundIdsInChunk = [];

                foreach ($chunk as $row) {
                    $outOrderCode   = $row['OutOrderCode'] ?? null;
                    $productBarcode = $row['Product Barcode'] ?? null;
                    $actualQty      = $row['ActualQuantity'] ?? 0;

                    if (!$outOrderCode || !$productBarcode) continue;

                    // Cari Inbound Header
                    $inbound = InboundRequest::where('fulfillment_order_no', $outOrderCode)->first();

                    if ($inbound) {
                        // Update Detail SKU
                        InboundRequestDetail::where('inbound_request_id', $inbound->id)
                            ->where('fulfillment_sku', $productBarcode)
                            ->update(['received_good' => (int)$actualQty]);

                        $inboundIdsInChunk[] = $inbound->id;
                    }
                }

                // 3. Sync Status untuk Inbound yang ada di chunk ini
                $this->syncInboundStatus(array_unique($inboundIdsInChunk));
            });
        });

        // Hapus file setelah selesai
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    protected function syncInboundStatus(array $ids)
    {
        foreach ($ids as $id) {
            $inbound = InboundRequest::with('details')->find($id);
            if ($inbound) {
                $totalRequested = $inbound->details->sum('requested_quantity');
                $totalReceived  = $inbound->details->sum('received_good');

                if ($totalReceived >= $totalRequested && $totalRequested > 0) {
                    $inbound->update(['status' => 'Completed']);
                } elseif ($totalReceived > 0) {
                    $inbound->update(['status' => 'Processing']);
                }
            }
        }
    }
}

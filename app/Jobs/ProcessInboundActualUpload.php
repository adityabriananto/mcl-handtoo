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
        $allRows = (new FastExcel)->import($this->filePath);

        // Proses dalam chunk besar untuk efisiensi
        $allRows->chunk(100)->each(function ($chunk) {
            DB::transaction(function () use ($chunk) {
                $orderCodes = $chunk->pluck('OutOrderCode')->filter()->unique();

                // Ambil semua inbound yang relevan dalam satu query (Eager Load Details)
                $inbounds = InboundRequest::with('details')
                    ->whereIn('fulfillment_order_no', $orderCodes)
                    ->whereNotIn('status', ['Completely', 'Partially', 'Cancelled'])
                    ->get()
                    ->keyBy('fulfillment_order_no');

                $impactedInboundIds = [];

                foreach ($chunk as $row) {
                    $outOrderCode   = $row['OutOrderCode'] ?? null;
                    $productBarcode = $row['Product Barcode'] ?? null;
                    $actualQty      = (int)($row['ActualQuantity'] ?? 0);

                    if (!$outOrderCode || !$productBarcode || !isset($inbounds[$outOrderCode])) continue;

                    $inbound = $inbounds[$outOrderCode];

                    // Update detail Child/Single
                    InboundRequestDetail::where('inbound_order_id', $inbound->id)
                        ->where('fulfillment_sku', $productBarcode)
                        ->update(['received_good' => $actualQty]);

                    $inbound->refresh();

                    $impactedInboundIds[] = $inbound->id;
                }

                // Jalankan sinkronisasi status dan akumulasi ke Parent
                $this->syncInboundStatus(array_unique($impactedInboundIds));
            });
        });

        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    protected function syncInboundStatus(array $ids)
    {
        foreach ($ids as $id) {
            $inbound = InboundRequest::with('details')->find($id);
            if (!$inbound) continue;

            // 1. Update status Inbound (Child atau Single)
            $status = $this->calculateInboundStatus($inbound);
            $inbound->update(['status' => $status]);

            // 2. Jika ini Child, akumulasi angka ke Parent dan update status Parent
            if ($inbound->parent_id) {
                $this->syncParentData($inbound->parent_id);
            }
        }
    }

    /**
     * Update Angka Received Good dan Status di level Parent
     */
    private function syncParentData($parentId)
    {
        // 1. Ambil Parent beserta ID anak-anaknya
        $parent = InboundRequest::with('children:id,parent_id')->find($parentId);
        if (!$parent) return;

        $childIds = $parent->children->pluck('id');

        // --- STEP A: AKUMULASI QUANTITY KE PARENT ---
        // Gunakan DB Raw untuk performa atau kumpulkan total per SKU dari semua child
        $totalsPerSku = InboundRequestDetail::whereIn('inbound_order_id', $childIds)
            ->select('fulfillment_sku', DB::raw('SUM(received_good) as total_received'))
            ->groupBy('fulfillment_sku')
            ->get()
            ->keyBy('fulfillment_sku');

        foreach ($totalsPerSku as $sku => $data) {
            // Update baris SKU yang sesuai di level Parent
            InboundRequestDetail::where('inbound_order_id', $parent->id)
                ->where('fulfillment_sku', $sku)
                ->update(['received_good' => $data->total_received]);
        }

        // --- STEP B: UPDATE STATUS PARENT ---
        // Ambil status terbaru dari database, jangan pakai yang ada di memori
        $childrenStatuses = InboundRequest::where('parent_id', $parent->id)
            ->pluck('status')
            ->toArray();

        $newParentStatus = 'Inbound in Process'; // Default

        if (empty($childrenStatuses)) {
            $newParentStatus = $parent->status;
        } elseif (collect($childrenStatuses)->every(fn($s) => $s === 'Completely')) {
            $newParentStatus = 'Completely';
        } elseif (collect($childrenStatuses)->contains('Partially') || collect($childrenStatuses)->contains('Completely')) {
            // Jika ada yang sudah mulai diterima, parent jadi Partially
            $newParentStatus = 'Partially';
        }

        $parent->update(['status' => $newParentStatus]);
    }

    private function calculateInboundStatus($inbound)
    {
        $totalRequested = $inbound->details->sum('requested_quantity');
        $totalReceived  = $inbound->details->sum('received_good');

        if ($totalRequested <= 0) return $inbound->status;

        if ($totalReceived >= $totalRequested) return 'Completely';
        if ($totalReceived > 0) return 'Partially';

        return 'Inbound in Process';
    }
}

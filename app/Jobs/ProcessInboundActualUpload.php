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
                    ->whereNotIn('status', ['Completed', 'Partial Completed'])
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
        // Load Parent dengan Child dan semua Detailnya
        $parent = InboundRequest::with(['children.details', 'details'])->find($parentId);
        if (!$parent) return;

        // --- STEP A: AKUMULASI QUANTITY KE PARENT ---
        // Kita looping detail milik parent untuk diupdate angkanya dari total child
        foreach ($parent->details as $parentDetail) {
            $totalReceivedFromChildren = InboundRequestDetail::whereIn('inbound_order_id', $parent->children->pluck('id'))
                ->where('fulfillment_sku', $parentDetail->fulfillment_sku)
                ->sum('received_good');

            $parentDetail->update(['received_good' => $totalReceivedFromChildren]);
        }

        // --- STEP B: UPDATE STATUS PARENT ---
        $childrenStatus = $parent->children()->pluck('status')->toArray();

        if (in_array('Partial Completed', $childrenStatus)) {
            $newParentStatus = 'Partial Completed';
        } else {
            $allCompleted = $parent->children()->count() === $parent->children()->where('status', 'Completed')->count();
            $newParentStatus = $allCompleted ? 'Completed' : 'Processing';
        }

        $parent->update(['status' => $newParentStatus]);
    }

    private function calculateInboundStatus($inbound)
    {
        $totalRequested = $inbound->details->sum('requested_quantity');
        $totalReceived  = $inbound->details->sum('received_good');

        if ($totalRequested <= 0) return $inbound->status;

        if ($totalReceived >= $totalRequested) return 'Completed';
        if ($totalReceived > 0) return 'Partial Completed';

        return 'Processing';
    }
}

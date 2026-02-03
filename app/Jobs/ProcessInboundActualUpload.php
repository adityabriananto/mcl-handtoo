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

        $allRows->chunk(100)->each(function ($chunk) {
            DB::transaction(function () use ($chunk) {
                $inboundIdsInChunk = [];

                foreach ($chunk as $row) {
                    $outOrderCode   = $row['OutOrderCode'] ?? null;
                    $productBarcode = $row['Product Barcode'] ?? null;
                    $actualQty      = $row['ActualQuantity'] ?? 0;

                    if (!$outOrderCode || !$productBarcode) continue;

                    // TAMBAHKAN PENGECEKAN STATUS DI SINI
                    // Hanya ambil data yang statusnya BUKAN Completed dan BUKAN Partial Completed
                    $inbound = InboundRequest::where('fulfillment_order_no', $outOrderCode)
                        ->whereNotIn('status', ['Completed', 'Partial Completed']) // Proteksi data
                        ->first();

                    if ($inbound) {
                        InboundRequestDetail::where('inbound_request_id', $inbound->id)
                            ->where('fulfillment_sku', $productBarcode)
                            ->update(['received_good' => (int)$actualQty]);

                        $inboundIdsInChunk[] = $inbound->id;
                    } else {
                        // Opsional: Log jika ada baris yang dilewati karena status sudah terkunci
                        \Log::info("SKU {$productBarcode} pada IO {$outOrderCode} di-skip karena status sudah Completed/Partial.");
                    }
                }

                $this->syncInboundStatus(array_unique($inboundIdsInChunk));
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

            // --- 1. Tentukan Status untuk Inbound Tersebut (Single atau Child) ---
            $status = $this->calculateInboundStatus($inbound);
            $inbound->update(['status' => $status]);

            // --- 2. Jika ini adalah Child, Update juga Status Parent-nya ---
            if ($inbound->parent_id) { // Asumsi kolom parent_id tersedia
                $this->syncParentStatus($inbound->parent_id);
            }
        }
    }

    /**
     * Logika status untuk Single atau Child individual
     */
    private function calculateInboundStatus($inbound)
    {
        $totalRequested = $inbound->details->sum('requested_quantity');
        $totalReceived  = $inbound->details->sum('received_good');

        if ($totalRequested <= 0) return $inbound->status;

        // Jika diterima >= diminta (Completed)
        if ($totalReceived >= $totalRequested) {
            return 'Completed';
        }

        // Jika ada yang diterima tapi kurang dari yang diminta (Partial Completed)
        if ($totalReceived > 0 && $totalReceived < $totalRequested) {
            return 'Partial Completed';
        }

        return 'Processing';
    }

    /**
     * Logika khusus untuk Parent berdasarkan status Child-nya
     */
    private function syncParentStatus($parentId)
    {
        $parent = InboundRequest::with('children')->find($parentId);
        if (!$parent) return;

        // Cek apakah ada child yang berstatus "Partial Completed"
        $hasPartialChild = $parent->children()->where('status', 'Partial Completed')->exists();

        if ($hasPartialChild) {
            $parent->update(['status' => 'Partial Completed']);
        } else {
            // Jika tidak ada yang partial, cek apakah semua sudah Completed
            $allCompleted = $parent->children()->count() === $parent->children()->where('status', 'Completed')->count();

            if ($allCompleted) {
                $parent->update(['status' => 'Completed']);
            } else {
                // Jika belum semua completed tapi tidak ada yang partial (berarti ada yang pending/processing)
                $parent->update(['status' => 'Processing']);
            }
        }
    }
}

<?php

namespace App\Services;

use App\Models\InboundRequestDetail;
use App\Models\MbMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboundMasterDataRecheckService
{
    /**
     * Re-check inbound details with missing master data and update fulfillment_sku
     * when a matching seller_sku is found in the newly uploaded/updated master data.
     *
     * @param array|null $sellerSkus Optional array of seller SKUs to limit re-check.
     *                               If null, all inbound details with missing master data will be re-checked.
     * @return int Number of inbound details updated.
     */
    public function recheckMissingMasterData(?array $sellerSkus = null): int
    {
        // Build query for active master data, optionally filtered by seller_sku
        $masterQuery = MbMaster::query()
            ->where('is_disabled', 0)
            ->whereNotNull('seller_sku')
            ->whereNotNull('fulfillment_sku');

        if (!empty($sellerSkus)) {
            $masterQuery->whereIn('seller_sku', $sellerSkus);
        }

        // Get the latest master record per seller_sku
        $masters = $masterQuery
            ->orderBy('id', 'desc')
            ->get()
            ->unique('seller_sku')
            ->keyBy('seller_sku');

        if ($masters->isEmpty()) {
            return 0;
        }

        $masterSellerSkus = $masters->keys()->toArray();

        // Get all fulfillment_sku values that exist in active master data
        $existingMasterFulfillmentSkus = MbMaster::query()
            ->where('is_disabled', 0)
            ->whereNotNull('fulfillment_sku')
            ->pluck('fulfillment_sku')
            ->map(fn($s) => trim($s))
            ->filter()
            ->values()
            ->toArray();

        // Find inbound details where seller_sku matches the newly uploaded master data
        // and fulfillment_sku is currently missing from master data.
        $detailsQuery = InboundRequestDetail::query()
            ->whereNotNull('seller_sku')
            ->whereIn('seller_sku', $masterSellerSkus)
            ->where(function ($q) use ($existingMasterFulfillmentSkus) {
                $q->whereNull('fulfillment_sku')
                  ->orWhere('fulfillment_sku', '')
                  ->orWhereNotIn(
                      DB::raw('TRIM(fulfillment_sku)'),
                      !empty($existingMasterFulfillmentSkus) ? $existingMasterFulfillmentSkus : ['__no_match__']
                  );
            });

        $updatedCount = 0;

        $detailsQuery->chunkById(500, function ($details) use ($masters, &$updatedCount) {
            foreach ($details as $detail) {
                $master = $masters->get($detail->seller_sku);

                if (!$master) {
                    continue;
                }

                // Only update if the new fulfillment_sku is different
                if (trim($detail->fulfillment_sku ?? '') === trim($master->fulfillment_sku)) {
                    continue;
                }

                try {
                    $detail->update([
                        'fulfillment_sku' => $master->fulfillment_sku,
                    ]);
                    $updatedCount++;
                } catch (\Exception $e) {
                    Log::error("Failed to update inbound detail {$detail->id} fulfillment_sku: " . $e->getMessage());
                }
            }
        });

        // Clear the master SKU cache so the inbound dashboard reflects the latest master data
        Cache::forget('admin_master_skus');

        return $updatedCount;
    }
}

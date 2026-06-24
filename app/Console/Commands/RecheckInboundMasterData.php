<?php

namespace App\Console\Commands;

use App\Services\InboundMasterDataRecheckService;
use Illuminate\Console\Command;

class RecheckInboundMasterData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inbound:recheck-master-data {--seller-sku=* : Limit re-check to specific seller SKUs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-check inbound details with missing master data and update fulfillment_sku when matching seller_sku is found.';

    /**
     * Execute the console command.
     */
    public function handle(InboundMasterDataRecheckService $service): int
    {
        $sellerSkus = $this->option('seller-sku');
        $sellerSkus = !empty($sellerSkus) ? $sellerSkus : null;

        $this->info('Re-checking inbound details with missing master data...');

        $updatedCount = $service->recheckMissingMasterData($sellerSkus);

        $this->info("Done. {$updatedCount} inbound detail(s) updated.");

        return Command::SUCCESS;
    }
}

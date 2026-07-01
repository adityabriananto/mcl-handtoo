<?php

namespace App\Jobs;

use App\Services\InboundMasterDataRecheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecheckInboundMasterDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array|null
     */
    protected $sellerSkus;

    /**
     * Create a new job instance.
     *
     * @param array|null $sellerSkus Optional seller SKUs to limit re-check.
     */
    public function __construct(?array $sellerSkus = null)
    {
        $this->sellerSkus = $sellerSkus;
    }

    /**
     * Execute the job.
     */
    public function handle(InboundMasterDataRecheckService $service): void
    {
        $service->recheckMissingMasterData($this->sellerSkus);
    }
}

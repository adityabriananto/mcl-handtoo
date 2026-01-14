<?php

namespace App\Jobs;

use App\Models\InboundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        $spreadsheet = IOFactory::load($this->filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $parentIds = [];

        foreach ($rows as $index => $data) {
            if ($index === 0) continue; // Skip header

            $refNum = trim($data[0] ?? '');
            $ioNum  = trim($data[1] ?? '');

            if (empty($refNum) || empty($ioNum)) continue;

            $inbound = InboundRequest::where('reference_number', $refNum)->first();

            if ($inbound) {
                $inbound->update([
                    'inbound_order_no' => $ioNum,
                    'status' => 'Processing'
                ]);

                if ($inbound->parent_id) {
                    $parentIds[] = $inbound->parent_id;
                }
            }
        }

        if (!empty($parentIds)) {
            InboundRequest::whereIn('id', array_unique($parentIds))->update(['status' => 'Processing']);
        }

        // Hapus file sementara setelah selesai
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }
}

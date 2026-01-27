<?php

namespace App\Imports;

use App\Models\MbOrderStaging;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;

class MbOrderImport implements ToModel, WithChunkReading, WithStartRow
{
    public function __construct(public string $formatType, public string $batchId) {}

    public function startRow(): int { return 2; }
    public function chunkSize(): int { return 1000; }

    public function model(array $row)
    {
        $cleanRow = array_map(fn($val) => is_string($val) ? trim($val) : $val, $row);
        $isF1 = ($this->formatType === 'format_1');

        // Update progress per row di database
        DB::table('mb_import_batches')->where('batch_id', $this->batchId)->increment('processed_rows');

        return new MbOrderStaging([
            'package_no'        => $isF1 ? ($cleanRow[3] ?? null) : ($cleanRow[1] ?? null),
            'waybill_no'        => $isF1 ? ($cleanRow[2] ?? null) : ($cleanRow[0] ?? null),
            'external_order_no' => $isF1 ? ($cleanRow[1] ?? null) : ($cleanRow[4] ?? null),
            'order_code'        => $isF1 ? ($cleanRow[0] ?? null) : null,
            'courier_name'      => $isF1 ? ($cleanRow[6] ?? null) : ($cleanRow[3] ?? null),
            'source_format'     => $this->formatType,
            'full_payload'      => $cleanRow,
            'upload_batch_id'   => $this->batchId,
        ]);
    }
}

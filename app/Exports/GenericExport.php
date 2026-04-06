<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class GenericExport implements FromCollection, WithCustomCsvSettings
{
    use Exportable;

    protected $collection;

    public function __construct(array $arrayItem)
    {
        $this->collection = collect($arrayItem);
    }

    public function collection()
    {
        return $this->collection;
    }

    public function getCsvSettings(): array
    {
        return [
            'enclosure' => ''
        ];
    }
}

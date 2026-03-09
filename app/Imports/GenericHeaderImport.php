<?php

namespace App\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GenericHeaderImport extends DefaultValueBinder implements ToCollection, WithCustomValueBinder, WithHeadingRow, WithMultipleSheets
{
    public function bindValue(Cell $cell, $value)
    {
        if (is_numeric($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $rows)
    {
        return $rows->toArray();
    }
}

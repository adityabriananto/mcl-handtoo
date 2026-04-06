<?php

namespace App\Traits;

use App\Imports\GenericImport;
use App\Imports\GenericHeaderImport;
use App\Exports\GenericExport;
use App\Exports\ViewExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

trait DocumentTrait
{
    public function loadExcelRowtoArray($file) {
        $import      = new GenericHeaderImport();
        $arrData = Excel::toArray($import, $file);
        // delete temp file
        unlink($file);
        return $arrData;
    }

    public function createExcelFromArray(array $arr, string $filename)
    {
        $finFilename = $filename;
        (new GenericExport($arr))->store(
            $finFilename,
            'temp-storage'
        );
        $filePath = storage_path().'/temp-storage/'.$finFilename;
        return $filePath;

    }
}

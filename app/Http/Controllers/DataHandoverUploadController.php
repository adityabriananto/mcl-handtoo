<?php

namespace App\Http\Controllers;

use App\Models\DataUpload;
use Illuminate\Http\Request;
use App\Models\HandoverDetail;
use Illuminate\Support\Facades\DB;

class DataHandoverUploadController extends Controller
{

    public function index() {
        return view('handover_upload.index');
    }

    public function store(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:5120' // Maks 5MB
        ]);

        $file = $request->file('csv_file');

        // 2. Gunakan Transaction agar jika ada error di tengah, database tetap bersih
        DB::beginTransaction();

        try {
            $handle = fopen($file->getRealPath(), 'r');

            // Lewati baris pertama (header)
            fgetcsv($handle);

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

                $handoverDetail = DataUpload::where('airwaybill', $data[2])->first();

                $handoverDetail = DataUpload::firstOrCreate([
                    'airwaybill' => $data[2]
                ]);
                $handoverDetail->order_number = $data[39] ?? null;
                $handoverDetail->owner_code   = $data[20] ?? null;
                $handoverDetail->owner_name   = $data[21] ?? null;
                $handoverDetail->qty          = $data[30] ?? 0;
                $handoverDetail->platform_name = $data[7] ?? null;
                $handoverDetail->save();
            }

            fclose($handle);
            DB::commit();

            return back()->with('success', 'Data Handover berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            // Tutup handle file jika terjadi error agar tidak mengunci file
            if(isset($handle)) fclose($handle);

            return back()->with('error', 'Terjadi kesalahan pada baris data: ' . $e->getMessage());
        }
    }
}

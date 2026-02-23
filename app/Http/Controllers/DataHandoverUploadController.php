<?php

namespace App\Http\Controllers;

use App\Models\DataUpload;
use Illuminate\Http\Request;
use App\Models\HandoverDetail;
use Illuminate\Support\Facades\DB;
use App\Jobs\HandoverUploadJob;

class DataHandoverUploadController extends Controller
{

    public function index() {
        return view('handover_upload.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:10240' // ditingkatkan ke 10MB
        ]);

        try {
            $file = $request->file('csv_file');

            // Simpan file ke folder temp di storage/app/uploads
            $fileName = 'handover_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads', $fileName);
            $fullPath = \Storage::disk('local')->path($path);

            // Lempar ke Background Job
            HandoverUploadJob::dispatch($fullPath)->onQueue('handover-upload');;

            return back()->with('success', 'File berhasil diunggah! Data sedang diproses di background.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses file: ' . $e->getMessage());
        }
    }
}

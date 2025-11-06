<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

use App\Models\HandoverBatch;
use App\Models\HandoverDetail;

class HandoverController extends Controller
{
    /**
     * Menampilkan halaman utama Handover Station.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $allCarriers = config('handover.all_carriers');

        return view('handover.station', [
            'allCarriers' => $allCarriers,
        ]);
    }

    // --- LOGIKA START BATCH ---

    /**
     * Memulai sesi Handover Batch baru dan membuat entri di DB.
     */
    public function setBatch(Request $request)
    {
        $validCarriers = config('handover.all_carriers');

        $validator = Validator::make($request->all(), [
            'handover_id' => 'required|string|max:50|unique:handover_batches,handover_id',
            'three_pl' => 'required|string|in:' . implode(',', $validCarriers),
        ], [
            'handover_id.unique' => 'Batch ID ini sudah digunakan. Mohon cek Riwayat.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('handover.index')
                             ->withErrors($validator)
                             ->withInput();
        }

        try {
            HandoverBatch::create([
                'handover_id' => $request->handover_id,
                'three_pl' => $request->three_pl,
                'user_id' => auth()->id() ?? 1,
                'status' => 'staging',
            ]);
        } catch (\Exception $e) {
            Session::flash('error', 'Gagal membuat batch di DB: ' . $e->getMessage());
             return redirect()->route('handover.index');
        }

        Session::put('current_batch_id', $request->handover_id);
        Session::put('current_three_pl', $request->three_pl);
        Session::put('batch_status', 'staged');
        Session::put('staged_awbs', []);

        Session::flash('success', 'Batch **' . $request->handover_id . '** dimulai untuk Carrier **' . $request->three_pl . '**! Data awal sudah disimpan di DB.');

        return redirect()->route('handover.index');
    }

    // --- LOGIKA SCANNING ---

    /**
     * Menambahkan AWB ke dalam sesi staged, melakukan validasi, dan menyimpannya ke database details.
     */
    public function scan(Request $request)
    {
        if (Session::get('batch_status') !== 'staged') {
            Session::flash('error', 'Sesi Handover belum dimulai. Silakan klik Start terlebih dahulu.');
            return redirect()->route('handover.index');
        }

        $validator = Validator::make($request->all(), [
            'awb_number' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return redirect()->route('handover.index')
                             ->withErrors($validator)
                             ->withInput();
        }

        $awb = trim(strtoupper($request->awb_number));
        $stagedAwbs = Session::get('staged_awbs', []);
        $batchId = Session::get('current_batch_id');
        $carrier = Session::get('current_three_pl');

        // 1. Cek Duplikasi di Sesi Saat Ini
        $isDuplicate = collect($stagedAwbs)->contains('airwaybill', $awb);
        if ($isDuplicate) {
            Session::flash('error', 'AWB **' . $awb . '** sudah pernah dipindai di Batch ini.');
            return redirect()->route('handover.index');
        }

        // 2. CEK AWB SUDAH ADA DI DATABASE DETAILS (GLOBAL CHECK)
        if (HandoverDetail::where('airwaybill', $awb)->exists()) {
             Session::flash('error', 'AWB **' . $awb . '** sudah pernah di-handover sebelumnya.');
             return redirect()->route('handover.index');
        }

        // 3. Pengecekan Prefix dan Status Cancelled
        if (!$this->isAwbValidForCarrier($awb, $carrier)) {
            Session::flash('error', 'AWB **' . $awb . '** tidak sesuai dengan Carrier **' . $carrier . '** atau merupakan AWB yang dibatalkan.');
            return redirect()->route('handover.index');
        }

        // 4. SIMPAN AWB LANGSUNG KE DATABASE DETAILS
        $scanTime = Carbon::now();
        try {
            HandoverDetail::create([
                'handover_id' => $batchId,
                'airwaybill' => $awb,
                'scanned_at' => $scanTime,
            ]);
        } catch (\Exception $e) {
            Session::flash('error', 'Gagal menyimpan AWB ke DB: ' . $e->getMessage());
            return redirect()->route('handover.index');
        }

        // 5. Tambahkan ke Sesi (Hanya untuk Display UI)
        $stagedAwbs[] = [
            'airwaybill' => $awb,
            'scanned_at' => $scanTime->toDateTimeString(),
        ];

        Session::put('staged_awbs', $stagedAwbs);

        Session::flash('success', 'AWB **' . $awb . '** berhasil ditambahkan. Total: ' . count($stagedAwbs) . ' AWBs.');

        return redirect()->route('handover.index');
    }

    /**
     * Menghapus AWB dari sesi staged dan database details.
     */
    public function remove(Request $request)
    {
        $awbToRemove = $request->awb_to_remove;
        $stagedAwbs = Session::get('staged_awbs', []);

        // HAPUS DARI DATABASE DETAILS
        try {
            HandoverDetail::where('airwaybill', $awbToRemove)->delete();
        } catch (\Exception $e) {
            Session::flash('error', 'Gagal menghapus AWB dari DB: ' . $e->getMessage());
            return redirect()->route('handover.index');
        }

        // Hapus dari Sesi
        $updatedAwbs = collect($stagedAwbs)->reject(function ($item) use ($awbToRemove) {
            return $item['airwaybill'] === $awbToRemove;
        })->values()->toArray();

        Session::put('staged_awbs', $updatedAwbs);

        Session::flash('warning', 'AWB **' . $awbToRemove . '** berhasil dihapus dari Batch dan DB.');

        return redirect()->route('handover.index');
    }

    // --- LOGIKA FINALISASI ---

    /**
     * Menyelesaikan sesi handover dan mengupdate data di database.
     */
    public function finalize()
    {
        $batchId = Session::get('current_batch_id');
        $awbs = Session::get('staged_awbs', []);

        if (empty($awbs)) {
            Session::flash('error', 'Tidak ada AWB yang dipindai. Finalisasi dibatalkan.');
            return redirect()->route('handover.index');
        }

        // UPDATE DATA BATCH KE DATABASE
        $batch = HandoverBatch::where('handover_id', $batchId)->first();
        if ($batch) {
            $batch->update([
                'total_awb' => count($awbs),
                'status' => 'completed',
                'finalized_at' => Carbon::now(),
            ]);
        }

        // Bersihkan Sesi
        Session::forget(['current_batch_id', 'current_three_pl', 'batch_status', 'staged_awbs']);

        Session::flash('success', 'Handover Batch **' . $batchId . '** berhasil diselesaikan dengan **' . count($awbs) . '** AWB. Data telah di-commit ke sistem.');

        return redirect()->route('handover.index');
    }

    // --- HELPER UNTUK LOGIKA BISNIS (AKTIF) ---

    /**
     * Helper untuk memvalidasi AWB berdasarkan prefix dan carrier yang aktif.
     */
    private function isAwbValidForCarrier($awb, $carrier)
    {
        // 1. Cek AWB Batal/Cancelled
        $cancelledAwbs = config('handover.cancelled_awbs', []);
        if (in_array($awb, $cancelledAwbs)) {
            return false;
        }

        // 2. Lewati pengecekan prefix jika carrier adalah 'Other 3PL'
        if ($carrier === 'Other 3PL') {
            return true;
        }

        // 3. Pengecekan Prefix
        $prefixMap = config('handover.prefix_map', []);
        $awbBelongsToCarrier = null;

        foreach ($prefixMap as $plName => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($awb, $prefix)) {
                    $awbBelongsToCarrier = $plName;
                    break 2;
                }
            }
        }

        // 4. Bandingkan Carrier
        if ($awbBelongsToCarrier) {
            if ($awbBelongsToCarrier !== $carrier) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }
}

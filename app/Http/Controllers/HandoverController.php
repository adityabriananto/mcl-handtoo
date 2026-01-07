<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\CancellationRequest;
use App\Models\ClientApi;
use App\Models\DataUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use GuzzleHttp\Client;

// Import Model yang dibutuhkan
use App\Models\HandoverBatch;
use App\Models\HandoverDetail;
use App\Models\TplPrefix;

class HandoverController extends Controller
{
    // --- HELPER UNTUK MENGAMBIL DATA KONFIGURASI AKTIF ---

    /**
     * Mengambil daftar nama 3PL (untuk UI dan validasi) dari TplPrefix yang aktif.
     * @return array
     */
    protected function getActiveCarriers()
    {
        // Ambil semua tpl_name dari konfigurasi yang aktif
        $carriers = TplPrefix::where('is_active', true)
                             ->pluck('tpl_name')
                             ->toArray();

        // Tambahkan opsi manual 'Other 3PL' jika masih diperlukan dalam logika bisnis
        $carriers[] = 'Other 3PL';

        return $carriers;
    }

    // --- LOGIKA START BATCH ---

    /**
     * Menampilkan halaman utama Handover Station.
     * Mengambil daftar carrier dari DB.
     *
     * @return \Illuminate\View\View
     */
    public function index()
{
    $batchId = session('current_batch_id');
    $stagedAwbs = [];

    if ($batchId) {
        // Ambil data langsung dari Database sebagai Source of Truth
        $stagedAwbs = HandoverDetail::where('handover_id', $batchId)
                        ->orderBy('scanned_at', 'desc')
                        ->get();
    }

    $allCarriers = $this->getActiveCarriers();

    return view('handover.station', [
        'stagedAwbs'  => $stagedAwbs,
        'allCarriers' => $allCarriers
    ]);
}

    /**
     * Memulai sesi Handover Batch baru dan membuat entri di DB.
     */
    public function setBatch(Request $request)
    {
        // Ambil daftar carrier yang valid secara dinamis dari DB untuk validasi
        $validCarriers = $this->getActiveCarriers();

        $validator = Validator::make($request->all(), [
            'handover_id' => 'required|string|max:50|unique:handover_batches,handover_id',
            // Gunakan implodasi daftar carrier dari DB untuk aturan 'in'
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

        Session::flash('success', 'Batch **' . $request->handover_id . '** dimulai untuk 3PL **' . $request->three_pl . '**! Data awal sudah disimpan di DB.');

        return redirect()->route('handover.index');
    }

    // --- LOGIKA SCANNING ---

    /**
     * Menambahkan AWB ke dalam sesi staged, melakukan validasi, dan menyimpannya ke database details.
     */
    public function scan(Request $request)
    {
        // 1. Cek Sesi Belum Dimulai
        if (Session::get('batch_status') !== 'staged') {
            Session::flash('error', 'Sesi Handover belum dimulai. Silakan klik Start terlebih dahulu.');
            return redirect()->route('handover.index');
        }

        // 2. Validasi Input
        $validator = Validator::make($request->all(), [
            'awb_number' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return redirect()->route('handover.index')
                            ->withErrors($validator)
                            ->withInput();
        }

        $awb = trim(strtoupper($request->awb_number));
        $batchId = Session::get('current_batch_id');
        $carrier = Session::get('current_three_pl');

        /**
         * SINKRONISASI SESI & DATABASE
         * Menghapus AWB dari session jika ternyata sudah dihapus oleh Cancellation API
         */
        $stagedAwbs = Session::get('staged_awbs', []);
        if (!empty($stagedAwbs)) {
            // Ambil daftar AWB yang saat ini benar-benar ada di database untuk batch ini
            $validAwbsInDb = HandoverDetail::where('handover_id', $batchId)
                                ->whereIn('airwaybill', collect($stagedAwbs)->pluck('airwaybill'))
                                ->pluck('airwaybill')
                                ->toArray();
            // Filter session: Hanya simpan yang masih ada di DB
            $stagedAwbs = collect($stagedAwbs)->filter(function($item) use ($validAwbsInDb) {
                return in_array($item['airwaybill'], $validAwbsInDb);
            })->values()->all();

            // Update session dengan data yang sudah bersih
            Session::put('staged_awbs', $stagedAwbs);
        }

        // 3. Cek Duplikasi di Sesi (Setelah dibersihkan)
        $isDuplicate = collect($stagedAwbs)->contains('airwaybill', $awb);
        if ($isDuplicate) {
            Session::flash('error', 'AWB **' . $awb . '** sudah pernah dipindai di Batch ini.');
            return redirect()->route('handover.index');
        }

        // 4. Cek AWB di Database Cancellation (Global Check untuk Batch lain)
        if (CancellationRequest::where('tracking_number',$awb)->exists()) {
            Session::flash('error', 'AWB **' . $awb . '** sudah di cancel.');
            return redirect()->route('handover.index');
        }
        // 5. Cek AWB di Database (Global Check untuk Batch lain)
        if (HandoverDetail::where('airwaybill', $awb)->exists()) {
            Session::flash('error', 'AWB **' . $awb . '** sudah pernah di-handover sebelumnya.');
            return redirect()->route('handover.index');
        }

        // 6. Pengecekan Validasi Carrier & Status (Prefix Check)
        if (!$this->isAwbValidForCarrier($awb, $carrier)) {
            Session::flash('error', 'AWB **' . $awb . '** tidak sesuai dengan 3PL **' . $carrier . '** atau merupakan AWB yang dibatalkan.');
            return redirect()->route('handover.index');
        }

        // 7. Simpan ke Database & Update Session
        $scanTime = Carbon::now();
        try {
            // Simpan ke DB
            HandoverDetail::create([
                'handover_id' => $batchId,
                'airwaybill'  => $awb,
                'scanned_at'  => $scanTime,
            ]);

            // Tambahkan ke Array Sesi
            $stagedAwbs[] = [
                'airwaybill' => $awb,
                'scanned_at' => $scanTime->toDateTimeString(),
            ];

            Session::put('staged_awbs', $stagedAwbs);
            Session::flash('success', 'AWB **' . $awb . '** berhasil ditambahkan. Total: ' . count($stagedAwbs) . ' AWBs.');

        } catch (\Exception $e) {
            Session::flash('error', 'Gagal menyimpan AWB ke DB: ' . $e->getMessage());
        }

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
        $awbs = HandoverDetail::where('handover_id',$batchId)->get();

        if (empty($awbs)) {
            Session::flash('error', 'Tidak ada AWB yang dipindai. Finalisasi dibatalkan.');
            return redirect()->route('handover.index');
        }

        // UPDATE DATA BATCH KE DATABASE
        $batch = HandoverBatch::where('handover_id', $batchId)->first();
        if ($batch) {
            $awbDetail = HandoverDetail::where('handover_id',$batchId)->get();
            // dd($awbDetail);
            $awbCancelCheck = HandoverDetail::where('handover_id',$batchId)
            ->where('is_cancelled',true)
            ->count();

            if($awbCancelCheck > 0) {
                Session::flash('error', 'Hapus AWB yang sudah di cancel.');
                return redirect()->route('handover.index');
            }

            foreach($awbDetail as $awb) {
                $dataDetails = DataUpload::where('airwaybill',$awb->airwaybill)->first();
                // dd($dataDetails);
                if($dataDetails) {
                    $clientApi = ClientApi::where('client_code',$dataDetails->owner_code)->first();
                    $data = [
                        'sales_order_number' => $dataDetails->order_number,
                        'platform_order_id'  => "-",
                        'owner_id'           => $dataDetails->owner_code,
                        'platform_name'      => $dataDetails->platform_name,
                        'biz_time'           => $awb->scanned_at,
                        'items' => [
                            [
                                'quantity'           => $dataDetails->qty,
                                'fulfillment_sku_id' => '-',
                                'owner_id'           => $dataDetails->owner_code,
                                'unit_price'         => '-',
                                'platform_item_id'   => '-',
                                'seller_id'          => '-',
                                'status'             => 'handover_to_3pl'
                            ]
                        ],
                        'seller_id' => "-",
                    ];
                    if($clientApi) {
                        $url = $clientApi->client_url;
                        $token = $clientApi->client_token;
                        $client = new Client();
                        $post  =  new \GuzzleHttp\Psr7\Request(
                            'POST',
                            $url,
                            [
                                'Content-Type' => 'application/json',
                                'api-key' => $token
                            ],
                            json_encode($data)
                        );
                        $response = $client->send($post);
                        // dd($response->getStatusCode());
                        ApiLog::create([
                            'endpoint'    => $url,
                            'method'      => 'POST',
                            'payload'     => json_encode($data),
                            'response'    => $response,
                            'status_code' => $response->getStatusCode()
                        ]);
                        if(
                            $response->getStatusCode() == 204 ||
                            $response->getStatusCode() == 200
                        ) {
                            $awb->is_sent_api = true;
                            $awb->save();
                        }
                    }
                }
                // $jsonResult = json_decode((string)$response->getBody(), true);
                // dd($jsonResult);
            }
            $batch->update([
                'total_awb' => count($awbs),
                'status' => 'completed',
                'finalized_at' => Carbon::now()
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
        // 1. Cek AWB Batal/Cancelled (Tetap menggunakan config jika data ini statis)
        // Jika data cancelled AWB juga harus dinamis, Anda harus membuat Model/Tabel baru.
        $cancelledAwbs = config('handover.cancelled_awbs', []);
        if (in_array($awb, $cancelledAwbs)) {
            return false;
        }

        // 2. Lewati pengecekan prefix jika carrier adalah 'Other 3PL'
        if ($carrier === 'Other 3PL') {
            return true;
        }

        // 3. AMBIL DATA PREFIX AKTIF DARI DATABASE BERDASARKAN CARRIER YANG DIPILIH
        $config = TplPrefix::where('tpl_name', $carrier)
                           ->where('is_active', true)
                           ->first();

        // Jika konfigurasi carrier tidak ditemukan atau tidak memiliki prefix, anggap tidak valid
        if (!$config || empty($config->prefixes)) {
            return false;
        }

        // Pastikan AWB (yang sudah di-uppercase) cocok dengan salah satu prefix
        $prefixes = $config->prefixes; // Ini sudah array karena JSON cast di Model TplPrefix

        foreach ($prefixes as $prefix) {
            // str_starts_with sudah case-insensitive karena AWB sudah di-uppercas, tapi pastikan prefix juga uppercase
            if (str_starts_with($awb, strtoupper($prefix))) {
                return true;
            }
        }

        return false;
    }

    public function clearBatch()
    {
        $handoverId = session('current_batch_id');

        if ($handoverId) {
            // 1. Hapus entri di HandoverAWB (AWBs yang sudah discan di database)
            HandoverDetail::where('handover_id', $handoverId)->delete();

            // 2. Hapus entri di HandoverBatch (Batch itu sendiri)
            HandoverBatch::where('handover_id', $handoverId)->delete();

            // 3. Hapus semua data sesi yang terkait dengan batch aktif
            Session::forget(['batch_status', 'current_batch_id', 'current_three_pl', 'staged_awbs']);

            return redirect()->route('handover.index')->with('success', "Batch **$handoverId** berhasil dibatalkan dan dihapus.");
        }

        return redirect()->route('handover.index')->with('error', 'Tidak ada batch aktif untuk dihapus.');
    }

    public function checkCount()
    {
        $batchId = session('current_batch_id');

        if (!$batchId) {
            return response()->json(['hash' => '']);
        }

        // Ambil semua AWB dan status cancel-nya dalam satu string
        $data = HandoverDetail::where('handover_id', $batchId)
                    ->select('airwaybill', 'is_cancelled')
                    ->orderBy('airwaybill')
                    ->get()
                    ->toJson();

        // Buat hash unik dari data tersebut
        return response()->json([
            'hash' => md5($data),
            'count' => HandoverDetail::where('handover_id', $batchId)->count()
        ]);
    }
    public function getTableFragment()
    {
        $batchId = session('current_batch_id');
        $stagedAwbs = [];

        if ($batchId) {
            $stagedAwbs = HandoverDetail::where('handover_id', $batchId)
                            ->orderBy('scanned_at', 'desc')
                            ->get();
        }

        // Mengembalikan hanya view tabel (kita akan buat file baru atau gunakan fragment)
        return view('handover.partials.table', compact('stagedAwbs'))->render();
    }
}

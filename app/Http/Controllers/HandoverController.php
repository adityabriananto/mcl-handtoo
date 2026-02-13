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

        // Inisialisasi sebagai Collection kosong agar aman di count()
        $stagedAwbs = collect();

        if ($batchId) {
            $stagedAwbs = HandoverDetail::where('handover_id', $batchId)
                            ->orderBy('scanned_at', 'desc')
                            ->get();
        }

        $allCarriers = $this->getActiveCarriers();

        return view('handover.station', [
            'stagedAwbs'  => $stagedAwbs, // Langsung kirim objek Collection
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
        if (Session::get('batch_status') !== 'staged') {
            return redirect()->back()->with('error', 'Sesi Handover belum dimulai.');
        }

        $awb = trim(strtoupper($request->awb_number));
        $batchId = Session::get('current_batch_id');
        $carrier = Session::get('current_three_pl');

        // 1. Cek Duplikasi di Database (Hanya untuk Batch Aktif ini)
        $isDuplicate = HandoverDetail::where('handover_id', $batchId)
                        ->where('airwaybill', $awb)
                        ->exists();

        if ($isDuplicate) {
            return redirect()->back()->with('error', "AWB **$awb** sudah ada di daftar scan.");
        }

        // 2. Cek Global: Apakah sudah pernah di-handover di batch lain?
        if (HandoverDetail::where('airwaybill', $awb)->exists()) {
            return redirect()->back()->with('error', "AWB **$awb** sudah pernah di-handover sebelumnya.");
        }

        // 3. Cek Pembatalan (Cancellation Table)
        if (CancellationRequest::where('tracking_number', $awb)->exists()) {
            return redirect()->back()->with('error', "AWB **$awb** sudah di-cancel oleh sistem.");
        }

        // 4. Validasi Prefix Carrier
        if (!$this->isAwbValidForCarrier($awb, $carrier)) {
            return redirect()->back()->with('error', "AWB **$awb** tidak sesuai dengan 3PL **$carrier**.");
        }

        try {
            HandoverDetail::create([
                'handover_id' => $batchId,
                'airwaybill'  => $awb,
                'scanned_at'  => now(),
            ]);

            return redirect()->back()->with('success', "AWB **$awb** berhasil ditambahkan.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal simpan ke DB: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus AWB dari sesi staged dan database details.
     */
    public function remove(Request $request)
    {
        $awbToRemove = $request->awb_to_remove;
        $batchId = session('current_batch_id');

        try {
            HandoverDetail::where('airwaybill', $awbToRemove)
                        ->where('handover_id', $batchId)
                        ->delete();

            return redirect()->back()->with('warning', "AWB **$awbToRemove** berhasil dihapus.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    // --- LOGIKA FINALISASI ---

    /**
     * Menyelesaikan sesi handover dan mengupdate data di database.
     */
    // public function finalize()
    // {
    //     $batchId = Session::get('current_batch_id');
    //     $awbs = HandoverDetail::where('handover_id',$batchId)->get();

    //     if (empty($awbs)) {
    //         Session::flash('error', 'Tidak ada AWB yang dipindai. Finalisasi dibatalkan.');
    //         return redirect()->route('handover.index');
    //     }

    //     // UPDATE DATA BATCH KE DATABASE
    //     $batch = HandoverBatch::where('handover_id', $batchId)->first();
    //     if ($batch) {
    //         $awbDetail = HandoverDetail::where('handover_id',$batchId)->get();
    //         // dd($awbDetail);
    //         $awbCancelCheck = HandoverDetail::where('handover_id',$batchId)
    //         ->where('is_cancelled',true)
    //         ->count();

    //         if($awbCancelCheck > 0) {
    //             Session::flash('error', 'Hapus AWB yang sudah di cancel.');
    //             return redirect()->route('handover.index');
    //         }

    //         foreach($awbDetail as $awb) {
    //             $dataDetails = DataUpload::where('airwaybill',$awb->airwaybill)->first();
    //             // dd($dataDetails);
    //             if($dataDetails) {
    //                 $clientApi = ClientApi::where('client_code',$dataDetails->owner_code)->first();
    //                 $data = [
    //                     'sales_order_number' => $dataDetails->order_number,
    //                     'platform_order_id'  => "-",
    //                     'owner_id'           => $dataDetails->owner_code,
    //                     'platform_name'      => $dataDetails->platform_name,
    //                     'biz_time'           => $awb->scanned_at,
    //                     'items' => [
    //                         [
    //                             'quantity'           => $dataDetails->qty,
    //                             'fulfillment_sku_id' => '-',
    //                             'owner_id'           => $dataDetails->owner_code,
    //                             'unit_price'         => '-',
    //                             'platform_item_id'   => '-',
    //                             'seller_id'          => '-',
    //                             'status'             => 'handover_to_3pl'
    //                         ]
    //                     ],
    //                     'seller_id' => "-",
    //                 ];
    //                 if($clientApi) {
    //                     $url = $clientApi->client_url;
    //                     $token = $clientApi->client_token;
    //                     $client = new Client();
    //                     $post  =  new \GuzzleHttp\Psr7\Request(
    //                         'POST',
    //                         $url,
    //                         [
    //                             'Content-Type' => 'application/json',
    //                             'api-key' => $token
    //                         ],
    //                         json_encode($data)
    //                     );
    //                     $response = $client->send($post);
    //                     // dd($response->getStatusCode());
    //                     ApiLog::create([
    //                         'endpoint'    => $url,
    //                         'method'      => 'POST',
    //                         'payload'     => json_encode($data),
    //                         'response'    => $response,
    //                         'status_code' => $response->getStatusCode()
    //                     ]);
    //                     if(
    //                         $response->getStatusCode() == 204 ||
    //                         $response->getStatusCode() == 200
    //                     ) {
    //                         $awb->is_sent_api = true;
    //                         $awb->save();
    //                     }
    //                 }
    //             }
    //             // $jsonResult = json_decode((string)$response->getBody(), true);
    //             // dd($jsonResult);
    //         }
    //         $batch->update([
    //             'total_awb' => count($awbs),
    //             'status' => 'completed',
    //             'finalized_at' => Carbon::now()
    //         ]);
    //     }

    //     // Bersihkan Sesi
    //     Session::forget(['current_batch_id', 'current_three_pl', 'batch_status', 'staged_awbs']);

    //     Session::flash('success', 'Handover Batch **' . $batchId . '** berhasil diselesaikan dengan **' . count($awbs) . '** AWB. Data telah di-commit ke sistem.');

    //     return redirect()->route('handover.index');
    // }

    public function finalize(Request $request)
    {
        $batchId = session('current_batch_id');

        // 1. Ambil semua detail AWB di batch ini
        $details = HandoverDetail::where('handover_id', $batchId)->get();

        // 2. Cek apakah ada yang berstatus Cancelled
        $hasCancelled = $details->contains('is_cancelled', true);

        if ($hasCancelled) {
            return redirect()->back()->with('error', 'Gagal Finalize! Terdapat AWB yang dibatalkan (Cancelled) di dalam daftar. Harap hapus terlebih dahulu.');
        }

        if ($details->isEmpty()) {
            return redirect()->back()->with('error', 'Gagal! Tidak ada AWB untuk diselesaikan.');
        }

        DB::beginTransaction();
        try {
            foreach($details as $awb) {
                $dataDetails = DataUpload::where('airwaybill',$awb->airwaybill)->first();
                // dd($dataDetails);
                if($dataDetails) {
                    $clientApi = ClientApi::where('client_code',$dataDetails->owner_code)->first();
                    $data = [
                        'sales_order_number' => $dataDetails->order_number,
                        'platform_order_id'  => null,
                        'owner_id'           => $dataDetails->owner_code,
                        'platform_name'      => $dataDetails->platform_name,
                        'biz_time' => Carbon::parse($awb->scanned_at, 'Asia/Jakarta')->setTimezone('UTC')->format('Y-m-d\TH:i:s.000\Z'),
                        'items' => [
                            [
                                'quantity'           => $dataDetails->qty,
                                'fulfillment_sku_id' => null,
                                'owner_id'           => $dataDetails->owner_code,
                                'unit_price'         => null,
                                'platform_item_id'   => null,
                                'seller_id'          => null,
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
                            'client_name' => $clientApi->client_name,
                            'api_type'    => 'HandoverWebhook',
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
            }
            // 3. Update status batch menjadi completed
            HandoverBatch::where('handover_id', $batchId)->update([
                'total_awb' => count($details),
                'status' => 'completed',
                'finalized_at' => now()
            ]);

            // 4. Bersihkan Session Scan
            session()->forget(['current_batch_id', 'batch_status', 'current_three_pl']);

            DB::commit();
            return redirect()->route('handover.index')->with('success', 'Batch ' . $batchId . ' berhasil diselesaikan!');

        } catch (\Exception $e) {
            DB::rollBack();
            ApiLog::create([
                'client_name' => isset($clientApi) ? $clientApi->client_name : "SYSTEM_ERROR",
                'api_type'    => 'HandoverWebhook_Error',
                'endpoint'    => $url ?? request()->fullUrl(),
                'method'      => 'POST',
                // Jika $data tidak ada, ambil semua input request sebagai payload
                'payload'     => json_encode($data ?? request()->all()),
                // Simpan pesan error sebagai response agar mudah di-debug
                'response'    => json_encode([
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]),
                'status_code' => 500
            ]);
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
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
        if (!$batchId) return response()->json(['hash' => '', 'count' => 0]);

        $data = HandoverDetail::where('handover_id', $batchId)
                    ->orderBy('scanned_at', 'desc')
                    ->get();

        return response()->json([
            'hash' => md5(json_encode($data)),
            'count' => $data->count()
        ]);
    }

    public function getTableFragment()
    {
        $batchId = session('current_batch_id');
        $stagedAwbs = HandoverDetail::where('handover_id', $batchId)
                        ->orderBy('scanned_at', 'desc')
                        ->get();

        return response()->json([
            'html' => view('handover.partials.table', compact('stagedAwbs'))->render(),
            'has_cancelled' => $stagedAwbs->contains('is_cancelled', true),
            'count' => $stagedAwbs->count(),
            'hash' => md5(json_encode($stagedAwbs))
        ]);
    }
}

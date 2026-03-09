<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fdcam;
use App\Models\FdcamItem;
use App\Traits\DocumentTrait;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Excel;
use Carbon\Carbon;
use ZipArchive;
use Illuminate\Support\Facades\Storage;

class FdReceivingDashboardController extends Controller
{
    //
    use DocumentTrait;
    public function index(Request $request) {
        // dd($request);
        $fdCamItem = FdcamItem::whereNotNull("manufacture_barcode")
        ->with('fdCam')
        ->leftJoin('fdcams','fdcams.id','=','fdcam_items.fdcam_id')
        ->whereNotNull('tracking_number')
        ->orderByDesc('fdcam_items.created_at')
        ;
        // dd($fdCamItem);
        $request->session()->put('fdcr_data_filter_first', $fdCamItem->get());
        return view("fdcr/dashboard", ['fdCamItem' => $fdCamItem->paginate(20)]);
    }

    public function store(Request $request, FdcamItem $fdCamItem) {
        $fdCamItem      = $fdCamItem->newQuery();
        $trackingNumber = $request->tracking_number;
        $orderNumber    = $request->order_number;
        $mb             = $request->manufacture_barcode;
        $parcelType     = $request->type;
        $quality        = $request->quality;
        $dateStart      = $request->start_date;
        $dateEnd        = $request->end_date;
        $owner          = $request->owner;
        $tpl            = $request->tpl;

        if(!empty($mb)) {
            $fdCamItem->where('manufacture_barcode', 'LIKE','%'.$mb.'%');
        }
        if(!empty($owner)) {
            $fdCamItem->where('owner', 'LIKE', '%'.$owner.'%');
        }
        if(
            !empty($dateStart) && !empty($dateEnd)
        ) {
            if(
                strtotime($dateStart) <= strtotime($dateEnd)
            ) {
                $fdCamItem->whereRaw('DATE(fdcam_items.created_at) >= \''.$dateStart.'\'')
                ->whereRaw('DATE(fdcam_items.created_at) <= \''.$dateEnd.'\'');
            } else {
                $fdCamItem->where('created_at', $dateStart);
            }
        }

        $fdCamItem->with('fdCam')
        ->leftJoin('fdcams','fdcams.id','=','fdcam_items.fdcam_id');
        if(!empty( $trackingNumber )) {
            $fdCamItem->where('tracking_number', 'LIKE','%'.$trackingNumber.'%');
        }
        if(!empty( $orderNumber )) {
            $fdCamItem->where('order_number', 'LIKE','%'.$orderNumber.'%');
        }
        if(!empty( $parcelType )) {
            $fdCamItem->where('parcel_type', $parcelType);
        }
        if(!empty( $quality )) {
            $fdCamItem->where('quality', $quality);
        }
        if(!empty($tpl)) {
            $fdCamItem->where('tpl', 'LIKE', '%'.$tpl.'%');
        }

        $fdCamItem->orderByDesc('fdcam_items.created_at');
        // dd($fdCamItem);
        $request->session()->put('fdcr_data_filter', $fdCamItem->get());
        return view("fdcr/dashboard", ['fdCamItem' => $fdCamItem->paginate(20)]);
    }

    public function download(Request $request)
    {
        if (!$request->has('tracking_number') || empty($request->tracking_number)) {
            return back()->with('NOTIF_DANGER', 'Tracking number is missing.');
        }

        $filePath = $request->file;

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        $newFileName = $request->tracking_number . '.' . $extension;

        return \Storage::download($filePath, $newFileName);
    }

    public function batchDownloadVideo(Request $request) {
        $filter = $request->session()->get('fdcr_data_filter_first');
        if($request->session()->get('fdcr_data_filter') != null) {
            $filter = $request->session()->get('fdcr_data_filter');
        }
        // dd($filter);
        $vidTem;
        foreach($filter as $key => $value) {
            // dd($value->recording);
            $vidTem[$key] = [
                "video" => $value->recording,
                "tracking_number" => $value->tracking_number
            ];
        }
        if (empty($vidTem)) {
            session()->flash('NOTIF_WARNING', 'No videos to download based on your filter.');
            return redirect()->route('fdcr-dashboard.index');
        }

        $zipFileName = 'batch_videos_download_' . now()->format('YmdHis') . '.zip';
        $zipFilePath = storage_path('app/' . $zipFileName);

        $zip = new ZipArchive();

        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($vidTem as $path) {
                // dd($path);
                $disk = Storage::disk('local');
                $videoPath = $path['video'];
                $trackingNumber = $path['tracking_number'];
                if ($disk->exists($videoPath)) {
                // Ambil ekstensi file asli
                    $extension = pathinfo($videoPath, PATHINFO_EXTENSION);

                    // Buat nama file baru: tracking_number.extension
                    $newFileName = $trackingNumber . '.' . $extension;

                    // Tambahkan file ke dalam zip dengan nama baru
                    $zip->addFile($disk->path($videoPath), $newFileName);
                }
            }
        $zip->close();

        // Mengirim file zip sebagai respons download
        return response()->download($zipFilePath)->deleteFileAfterSend(true);

    } else {
        session()->flash('NOTIF_DANGER', 'Failed to create the zip file.');
        return redirect()->route('fdcr-dashboard.index');
    }

    }

    public function export(Request $request) {
        $fdData = $request->session()->get('fdcr_data_filter');

        if(empty($fdData)) {
            $fdData = $request->session()->get('fdcr_data_filter_first');
        }
        $arrData[] = [
            'created_timestamp',
            '3pl',
            'tracking_number',
            'order_number',
            'parcel_type',
            'owner',
            'manufacture_barcode',
            'sku',
            'quality',
            'notes'
        ];

        foreach($fdData as $datum) {
            $arrData[] = [
                $datum->created_at,
                $datum->tpl,
                $datum->tracking_number,
                $datum->order_number,
                $datum->parcel_type,
                $datum->owner,
                $datum->manufacture_barcode,
                $datum->sku,
                $datum->quality,
                $datum->notes
            ];
            // dd($arrData);
        }
        $fileName = 'fdcr_export-'.Carbon::now()->toDateTimeString().'.xlsx';
        $result = $this->createExcelFromArray($arrData,$fileName);

        return response()->download($result);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Contracts\ReceivingParcelTypeInterface as Pti;
use App\Contracts\ReceivingQcResultInterface as Qcr;
use Validator;

use App\Models\Fdcam;
use App\Models\FdcamItem;


class FdcrReceivingController extends Controller
{

    public function index()
    {
        return view('fdcr.index', [
            'qcResultOptions' => [
                Qcr::GOOD       => 'Good',
                Qcr::DEFFECTIVE => 'Deffective',
                Qcr::REJECT_3PL => 'Reject to 3PL',
            ],
            'receivingOptions' => [
                Pti::FAILED_DELIVERY => 'Failed Delivery',
                Pti::CUSTOMER_RETURN => 'Customer Return',
            ],
            'tplOptions' => [
                'LEX'         => 'LEX',
                'JNE'         => 'JNE',
                'J&T'         => 'J&T',
                'J&T Cargo'   => 'J&T Cargo',
                'Ninjavan-ID' => 'Ninjavan-ID',
                'Indopaket'   => 'Indopaket',
                'Anteraja'    => 'Anteraja',
                'SPX'         => 'SPX',
                'Grab ID'     => 'Grab ID',
                'Gojek'       => 'Gojek',
                'Sicepat'     => 'Sicepat',
                'Pos Indo'    => 'Pos Indo',
                'ID Express'  => 'ID Express',
                'GTL'         => 'GTL',
                'Tiki'        => 'Tiki',
                'Lion Parcel' => 'Lion Parcel',
                'SAP'         => 'SAP',
                'Wahana'      => 'Wahana',
                'Blibli'      => 'Blibli',
            ]
        ]);
    }

    public function store(Request $request)
    {
        // dd($request);
        $validator = Validator::make($request->all(), [
            'tracking_number' => 'required|string',
            'tpl'             => 'required|string',
            'order_number'    => 'sometimes|nullable',
            'parcel_type'     => 'required|string',
            'video_file'      => 'required|string',
        ]);
        if ($validator->fails()) {
            session()->flash(NOTIF_DANGER, $validator->errors()->first());
            return redirect()->back();
        }
        $fdcam = new Fdcam([
            'tracking_number' => $request->tracking_number,
            'owner'           => $request->owner,
            'tpl'             => $request->tpl,
            'order_number'    => $request->order_number,
            'parcel_type'     => $request->parcel_type,
            'recording'       => $request->video_file,
        ]);
        $fdcam->save();

        for($i = 0; $i < count($request->manufacture_barcode); $i++) {
            $fdcamItem = new FdcamItem([
                'fdcam_id'            => $fdcam->id,
                'manufacture_barcode' => $request->manufacture_barcode[$i],
                'sku'                 => $request->sku[$i],
                'quality'             => $request->quality[$i],
                'notes'               => $request->notes[$i],
                'owner'               => $request->owner[$i],
            ]);
            $fdcamItem->save();
        }

        session()->flash(NOTIF_SUCCESS, 'Input Success!');
        // session()->flash('last_tpl', $request->input('tpl'));
        \Session::flash('last_tpl', $request->input('tpl'));

        // return redirect()->back();
        return redirect()->route('fdcr-receiving.store')->with('success', 'Data berhasil disimpan!');

    }

    public function video(Request $request)
    {
        $data = $request->file('video')->store();
        return response()->json(
            [
                'success' => true,
                'file'    => $data,
            ]
        );
    }

    public function trace(Request $request)
    {
        $data = null;
        if (!empty($request->search)) {
            $data = Fdcam::where('tracking_number', $request->search)
                         ->orWhere('order_number', $request->search)
                         ->get();
            if (count($data) == 0) {
                session()->flash(NOTIF_DANGER, 'No data found!');
            } else {
                session()->flash(NOTIF_SUCCESS, 'Data found!');
            }
        }
        return view('fdcr.list', [
            'data' => $data,
        ]);
    }

    public function download(Request $request)
    {
        return \Storage::download($request->file);
    }
}

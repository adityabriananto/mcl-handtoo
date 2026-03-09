@extends('layouts.master')

@section('page-title')
FD CR Receiving Dashboard
@endsection

@section('body')
<div class="flex flex-wrap flex-row w-full bg-gray-100 p-7 rounded shadow-sm">
    <div class="w-full md:w-full">
        <form action="{{ route('fdcr-dashboard.index') }}" method="POST" enctype="multipart/form-data" class="flex items-center">
            @csrf
            <label for="simple-search" class="sr-only">Search</label>
            <div class="relative w-full">
                <label>Tracking Number</label>
                <input type="text" id="tracking_number" name="tracking_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Tracking Number">
            </div>
            <div class="relative w-full">
                <label>Owner</label>
                <input type="text" id="owner" name="owner" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Owner">
            </div>
            <div class="relative w-full">
                <label>3PL</label>
                <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" id="tpl" name="tpl">
                    <option value="">Choose 3PL</option>
                    <option value="LEX">LEX</option>
                    <option value="JNE">JNE</option>
                    <option value="J&T">J&T</option>
                    <option value="J&T Cargo">J&T Cargo</option>
                    <option value="Ninjavan-ID">Ninjavan-ID</option>
                    <option value="Indopaket">Indopaket</option>
                    <option value="Anteraja">Anteraja</option>
                    <option value="SPX">SPX</option>
                    <option value="Grab ID">Grab ID</option>
                    <option value="Gojek">Gojek</option>
                    <option value="Sicepat">Sicepat</option>
                    <option value="Pos Indo">Pos Indo</option>
                    <option value="ID Express">ID Express</option>
                    <option value="GT">GTL</option>
                    <option value="Tik">Tiki</option>
                    <option value="Lio">Lion Parcel</option>
                    <option value="SA">SAP</option>
                    <option value="Wahan">Wahana</option>
                    <option value="Blibl">Blibli</option>
                </select>
            </div>
            <div class="relative w-full">
                <label>Order Number</label>
                <input type="text" id="order_number" name="order_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Order Number">
            </div>
            <div class="relative w-full">
                <label>Manufacture Barcode</label>
                <input type="text" id="manufacture_barcode" name="manufacture_barcode" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Manufacture Barcode">
            </div>
             <div class="relative w-full">
                <label>Parcel Type</label>
                <select id="type" name="type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" >
                    <option value="">Choose Type</option>
                    <option value="FAILED_DELIVERY">FD</option>
                    <option value="CUSTOMER_RETURN">CR</option>
                </select>
            </div>
            <div class="relative w-full">
                <label>Quality</label>
                <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" id="quality" name="quality">
                    <option value="">Choose Quality</option>
                    <option value="GOOD">Good</option>
                    <option value="DEFFECTIVE">Defective</option>
                    <option value="REJECT_TO_3PL">Reject to 3PL</option>
                </select>
            </div>
            <div class="relative w-full">
                <label>Start At</label>
                <input type="date" id="start_date" name="start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
            </div>
            <div class="relative w-full">
                <label>End At</label>
                <input type="date" id="end_date" name="end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
            </div>
            <div class="relative w-1/2">
                <label>Submit</label>
                <button type="submit"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">Submit
                </button>
            </div>
        </form>
    </div>
    <div class="w-1/12" style="margin-top:1%">
        <form action="{{ route('fdcr.export') }}" method="POST" enctype="multipart/form-data" class="flex items-center">
            @csrf
            <button type="submit"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">Export
            </button>
        </form>
    </div>
     <div class="w-1/12" style="margin-top:1%">
        <form action="{{ route('fdcr.batch-video-download') }}" method="POST" enctype="multipart/form-data" class="flex items-center">
            @csrf
            <button type="submit"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">Batch Download Video
            </button>
        </form>
    </div>
    <div class="w-full" style="margin-top:1%">
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-3">
                        Created Timestamp
                    </th>
                    <th scope="col" class="px-6 py-3">
                        3PL
                    </th>
                    <th scope="col" class="px-6 py-3">
                        TN Number
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Order Number
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Parcel Type
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Owner
                    </th>
                    <th scope="col" class="px-6 py-3">
                        MB Info
                    </th>
                    <th scope="col" class="px-6 py-3">
                        SKU Info
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Quality
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Notes
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Video Url
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($fdCamItem as $data)
                <tr>
                    <td>
                        {{$data->created_at}}
                    </td>
                    <td>
                        {{$data->tpl}}
                    </td>
                    <td>
                        {{$data->fdCam()->first()->tracking_number}}
                    </td>
                    <td>
                        {{$data->fdCam()->first()->order_number}}
                    </td>
                    <td>
                        {{$data->fdCam()->first()->parcel_type}}
                    </td>
                    <td>
                        {{ $data->owner }}
                    </td>
                    <td>
                        {{ $data->manufacture_barcode }}
                    </td>
                    <td>
                        {{ $data->sku }}
                    </td>
                    <td>
                        {{ $data->quality }}
                    </td>
                    <td>
                        {{ $data->notes }}
                    </td>
                    <td>
                        {{-- <a href={{$data->fdCam()->first()->recording}}>{{$data->fdCam()->first()->recording}}</a> --}}
                        {!! html()->form('post')->route('fdcr-dashboard.video-download')->class('flex flex-row flex-wrap w-full')->open() !!}
                        {!! html()->hidden('file', $data->recording) !!}
                        {!! html()->hidden('tracking_number', $data->tracking_number) !!}
                        {!! html()->tailwindButtonSubmit('Download') !!}
                        {!! html()->form()->close() !!}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $fdCamItem->links() }}
    </div>
</div>
@endsection

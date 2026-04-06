@extends('layouts.master')

@section('page-title')
FD CR Trace
@endsection

@section('body')
<div class="flex flex-wrap flex-row w-full bg-gray-100 p-5 rounded shadow-sm">
    {{ html()->form('get')->route('fdcr.trace')->class('flex flex-row flex-wrap w-full')->open() }}
        <div class="w-full">
            <div class="w-full p-2">
                {!! html()->tailwindInputText('search', 'Tracking Number / Order Number') !!}
            </div>
        </div>
        <div class="w-full p-2">
            <hr><br>
            {!! html()->tailwindButtonSubmit('Submit') !!}
        </div>
    {!! html()->form()->close() !!}
</div>

@if(!empty($data))
<br><br>
<hr>
<div class="flex flex-wrap flex-row w-full my-4">
    @foreach($data as $datum)
    <div class="flex flex-wrap w-full my-4 p-2 bg-gray-100 p-6">
        <div class="flex flex-wrap md:w-1/2 w-full">
            <div class="w-full mb-2">
                <h2 class="text-2xl font-bold text-blue-500">Order Info</h2>
            </div>
            <p class="text-lg">
                <b class="text-gray-700">Tracking Number:</b> {{ $datum->tracking_number }} <br>
                <b class="text-gray-700">Order Number:</b> {{ $datum->order_number }} <br>
                <b class="text-gray-700">Parcel Type:</b> {{ $datum->parcel_type }} <br>
                <br>
            </p>
            <div class="w-full mb-2 text-blue-500 hover:text-blue-400 cursor-pointer">
                {!! html()->form('post')->route('fdcr.video-download')->class('flex flex-row flex-wrap w-full')->open() !!}
                    {!! html()->hidden('file', $datum->recording) !!}
                    {!! html()->tailwindButtonSubmit('Download') !!}
                {!! html()->form()->close() !!}
            </div>
        </div>
        <div class="flex flex-wrap md:w-1/2 w-full">
            <div class="w-full mb-2">
                <h2 class="text-2xl font-bold text-blue-500">Items</h2>
            </div>
            <table class="table-auto border-collapse border border-gray-700 w-full">
                <thead>
                    <tr>
                        <th class="border font-bold text-lg bg-blue-500 text-white border-gray-600 p-4">MB</th>
                        <th class="border font-bold text-lg bg-blue-500 text-white border-gray-600 p-4">SKU</th>
                        <th class="border font-bold text-lg bg-blue-500 text-white border-gray-600 p-4">Quality</th>
                        <th class="border font-bold text-lg bg-blue-500 text-white border-gray-600 p-4">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($datum->items()->get() as $item)
                    <tr>
                        <td class="border border-gray-600 p-4">{{ $item->manufacture_barcode }}</td>
                        <td class="border border-gray-600 p-4">{{ $item->sku }}</td>
                        <td class="border border-gray-600 p-4">{{ $item->quality }}</td>
                        <td class="border border-gray-600 p-4">{{ $item->notes }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection

@section('page-script')
@endsection

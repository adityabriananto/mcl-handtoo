@extends('layouts.master')

@section('page-title')
FD CR Receiving
@endsection

@section('body')
<div class="flex flex-wrap flex-row w-full bg-gray-100 p-5 rounded shadow-sm">

    <template>
        <div class="w-full p-4 border border-gray-500 bg-gray-200 mb-2">
            <div class="w-full py-2">
                <div class="w-full p-2">
                    {!! html()->tailwindInputTextRequired('owner[]', 'Owner*') !!}
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindInputText('manufacture_barcode[]', 'MB') !!}
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindInputText('sku[]', 'SKU') !!}
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindInputSelect('quality[]', 'Quality', $qcResultOptions) !!}
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindInputText('notes[]', 'Notes') !!}
                </div>
                <div class="w-full p-2">
                    <button class="py-2 px-4 bg-red-500 hover:bg-red-400 text-white cursor-pointer del-iteminfo-button">x</button>
                </div>
            </div>
        </div>
    </template>

    {{ html()->form('POST')->route('fdcr-receiving.store')->class('flex flex-row flex-wrap w-full')->acceptsFiles()->open() }}
        <div class="w-full md:w-1/2">
            {!! html()->hidden('video_file') !!}
            <div class="w-full">
                <div class="w-full p-2">
                    <h2 class="text-lg md:text-2xl font-bold text-blue-500">
                        Order Info
                    </h2>
                </div>
               <div class="w-full p-2">
                    {!! html()->tailwindInputSelectRequired('tpl', '3PL*', $tplOptions, session('last_tpl', old('tpl'))) !!}
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindInputTextRequired('tracking_number', 'Tracking Number*') !!}
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindInputText('order_number', 'Order Number') !!}
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindInputSelect('parcel_type', 'Parcel Type*', $receivingOptions) !!}
                </div>
                <br>
                <hr>
                <br>
            </div>
            <div class="w-full">
                <div class="w-full p-2">
                    <h2 class="text-lg md:text-2xl font-bold text-blue-500">
                        Item Info
                    </h2>
                </div>
                <div class="w-full" id="item-info-container">
                    <div class="w-full p-4 border border-gray-500 bg-gray-200 mb-2">
                        <div class="w-full py-2">
                            <div class="w-full p-2">
                                {!! html()->tailwindInputTextRequired('owner[]', 'Owner*') !!}
                            </div>
                            <div class="w-full p-2">
                                {!! html()->tailwindInputTextRequired('manufacture_barcode[]', 'MB*') !!}
                            </div>
                            <div class="w-full p-2">
                                {!! html()->tailwindInputText('sku[]', 'SKU') !!}
                            </div>
                            <div class="w-full p-2">
                                {!! html()->tailwindInputSelect('quality[]', 'Quality*', $qcResultOptions) !!}
                            </div>
                            <div class="w-full p-2">
                                {!! html()->tailwindInputTextarea('notes[]', 'Notes') !!}
                            </div>
                            <div class="w-full p-2">
                                <button class="py-2 px-4 bg-red-500 hover:bg-red-400 text-white cursor-pointer del-iteminfo-button">x</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="w-full p-2">
                    {!! html()->tailwindGenericButton('Add Item', 'add-item') !!}
                </div>
            </div>
        </div>
        <div class="w-full md:w-1/2">
            <div class="w-full p-2">
                <h2 class="text-lg md:text-2xl font-bold text-blue-500">
                    Create Recording
                </h2>
            </div>
            <div class="w-full p-2">
                <video id='video' autoplay></video>
                <div class="w-full py-2">
                    {!! html()->tailwindGenericButton('Cam Access', 'request', 'blue') !!}
                    {!! html()->tailwindGenericButton('Start', 'start', 'green') !!}
                    {!! html()->tailwindGenericButton('Stop', 'stop', 'red') !!}
                </div>
                <div class="w-full py-2">
                    <ul id='ul' class="w-full">
                        <b>Direct Downloads List:</b>
                    </ul>
                </div>
            </div>
        </div>
        <div class="w-full p-2">
            <hr><br>
            {!! html()->tailwindButtonSubmit('Submit') !!}
        </div>
    {!! html()->form()->close() !!}
</div>
@endsection

@section('page-script')
<script src="{{ asset('js/cam.js') }}"></script>
<script>
    $(document).keypress(
    function(event){
        if (event.which == '13') {
        event.preventDefault();
        }
    });
    $('#tpl').on('change', function() {
        localStorage.setItem('last_tpl', $(this).val());
    });
</script>
@endsection

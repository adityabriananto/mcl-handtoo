<!DOCTYPE html>
<html>
<head>
    <title>Handover Manifest - {{ $batch->handover_id }}</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; }
        .container { padding: 30px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 24px; color: #333; margin: 0; }
        .metadata-left, .metadata-right { width: 48%; }
        .metadata-right { text-align: right; }
        .metadata-right img { max-height: 50px; margin-bottom: 5px; }
        .metadata-left h2 { font-size: 18px; margin-bottom: 5px; }

        /* Gaya Tabel Detail Paket */
        .package-details { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .package-details th, .package-details td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        .package-details th { background-color: #f2f2f2; }

        /* Gaya Signature */
        .signatures { width: 100%; margin-top: 50px; border-top: 1px dashed #aaa; padding-top: 20px; }
        .signature-col { width: 50%; float: left; text-align: center; }
        .signature-line { height: 1px; border-bottom: 1px solid #000; width: 80%; margin: 50px auto 5px; }
        .signature-label { font-size: 12px; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">

        {{-- Header --}}
        <div class="header">
            <h1>HANDOVER DOCUMENT</h1>
        </div>

        {{-- Metadata Batch & Logistics --}}
        <div class="metadata">
            <div class="metadata-left" style="float: left;">
                <h2>Batch ID: {{ $batch->handover_id }}</h2>

                {{-- Barcode (Asumsi milon/barcode terinstal) --}}
                <div class="barcode">
                    <img src="data:image/png;base64,{!! DNS1D::getBarcodePNG($batch->handover_id, 'C39', 1.5, 50) !!}" alt="barcode" style="width: 100%; height: 50px;" />
                </div>
            </div>

            <div class="metadata-right" style="float: right;">
                <p>Printed Date: {{ $printDate }}</p>
                <h3 style="margin-top: 5px;">3PL: {{ $batch->three_pl }}</h3>
            </div>
            <div style="clear: both;"></div>
        </div>

        {{-- Detail Paket --}}
        <h3>Package Details (Total: {{ $details->count() }})</h3>
        <table class="package-details">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>AWB Number</th>
                    <th>Handover Timestamp</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($details as $detail)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $detail->airwaybill }}</td>
                    <td>{{ $detail->scanned_at->format('Y-m-d H:i:s') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Area Tanda Tangan --}}
        <div class="signatures">
            <div class="signature-col" style="width: 50%; float: left;">
                <p class="signature-line"></p>
                <p>({{ auth()->user()->name ?? 'Outbound Team User' }})</p>
                <p class="signature-label">Outbound Team</p>
            </div>
            <div class="signature-col" style="width: 50%; float: right;">
                <p class="signature-line"></p>
                <p>({{ $batch->three_pl }} Driver / PIC Name)</p>
                <p class="signature-label">3PL PIC</p>
            </div>
            <div style="clear: both;"></div>
        </div>

    </div>
</body>
</html>

@php
    // Rows flow into up to three columns so a long tag stays readable and a short
    // one wraps instead of clipping. Four lines is what 18 mm holds at ~6pt.
    $perColumn = 4;
    $columns = array_chunk($rows, $perColumn);
    $font = (float) $settings->font_size_pt;
    $margin = (float) $settings->margin_mm;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $code }}</title>
    <style>
        /* Margins live on @page, not as padding on a wrapper: dompdf honours these
           exactly, whereas box-sizing on a padded div is unreliable and pushed the
           tag onto a second page. */
        @page {
            margin: {{ $margin }}mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            /* Helvetica is a PDF core font, so nothing is embedded and the tag stays
               a few KB instead of ~900 KB with DejaVu. It has no rupee glyph, which
               is why amounts print bare. */
            font-family: Helvetica, Arial, sans-serif;
            font-size: {{ $font }}pt;
            line-height: 1.25;
            color: #000;
        }

        .tag {
            width: 100%;
        }

        table.layout {
            width: 100%;
            border-collapse: collapse;
        }

        table.layout > tr > td,
        table.layout td {
            vertical-align: middle;
            padding: 0;
        }

        .identity {
            text-align: center;
            padding-right: 2mm;
            white-space: nowrap;
        }

        .identity .code {
            font-size: {{ $font + 3 }}pt;
            font-weight: bold;
        }

        .identity .net {
            font-size: {{ $font + 1 }}pt;
            font-weight: bold;
        }

        .shop {
            font-size: {{ max(4, $font - 1.5) }}pt;
            text-transform: uppercase;
            letter-spacing: 0.2pt;
        }

        table.fields {
            border-collapse: collapse;
        }

        table.fields td {
            padding: 0 1.5mm 0 0;
            white-space: nowrap;
            font-size: {{ $font }}pt;
        }

        table.fields td.k {
            font-weight: bold;
            padding-right: 0.6mm;
        }

        table.fields td.v {
            text-align: right;
            padding-right: 3mm;
        }

        .qr {
            text-align: right;
            width: {{ $qrSizeMm }}mm;
        }

        .qr img {
            width: {{ $qrSizeMm }}mm;
            height: {{ $qrSizeMm }}mm;
            /* Inline images carry a descender gap that pushes the tag onto a
               second page at this height. Block removes it. */
            display: block;
        }
    </style>
</head>

<body>
    <div class="tag">
        <table class="layout">
            <tr>
                <td class="identity">
                    @if ($shopName)
                        <div class="shop">{{ $shopName }}</div>
                    @endif
                    <div class="code">{{ $code }}</div>
                    <div class="net">({{ $netWeight }})</div>
                </td>

                @foreach ($columns as $column)
                    <td>
                        <table class="fields">
                            @foreach ($column as $row)
                                <tr>
                                    <td class="k">{{ $row['label'] }}:</td>
                                    <td class="v">{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                @endforeach

                @if ($qr)
                    <td class="qr">
                        <img src="{{ $qr }}" alt="{{ $code }}">
                    </td>
                @endif
            </tr>
        </table>
    </div>
</body>

</html>

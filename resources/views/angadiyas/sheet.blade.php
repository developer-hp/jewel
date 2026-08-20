{{--
    A4 sheet of angadiya slips.

    Laid out with a <table>, not flexbox or CSS grid: dompdf's support for modern
    layout is poor, and the item tag already taught us that lesson. Each row avoids
    an internal page break so a slip is never split across sheets.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Angadiya Slips</title>
    <style>
        @page {
            margin: 8mm;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            /* Helvetica is a PDF core font, so nothing is embedded and the sheet
               stays a few KB. It has no rupee glyph, hence the bare numbers. */
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #000;
        }

        table.sheet {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3mm;
        }

        table.sheet > tr,
        table.sheet tr {
            page-break-inside: avoid;
        }

        td.slot {
            width: {{ round(100 / $columns, 4) }}%;
            vertical-align: top;
            padding: 0;
        }

        td.empty {
            border: none;
        }

        .slip {
            border: 1.2pt solid #000;
            padding: 2.5mm;
            height: {{ $slipHeightMm }}mm;
        }

        .insurance {
            font-weight: bold;
            font-size: 11pt;
        }

        .to {
            font-weight: bold;
            font-size: 11pt;
        }

        .line {
            font-weight: bold;
            font-size: 10.5pt;
        }

        hr {
            border: none;
            border-top: 0.8pt solid #000;
            margin: 2mm 0 1.5mm;
        }

        .from-label {
            font-weight: bold;
            font-size: 9pt;
        }

        .from-line {
            font-weight: bold;
            font-size: 10pt;
        }

        .missing {
            font-size: 8pt;
            font-style: italic;
        }
    </style>
</head>

<body>
    <table class="sheet">
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $slip)
                    <td class="slot">
                        <div class="slip">
                            <div class="insurance">INSURANCE : {{ number_format((float) $slip->insurance_amount, 0) }}</div>
                            <div class="to">TO : {{ $slip->city }}</div>
                            <div class="line">{{ $slip->name }}</div>
                            <div class="line">{{ $slip->mobile }}</div>

                            <hr>

                            <div class="from-label">FROM</div>
                            @if ($from)
                                <div class="from-line">{{ $from['name'] }}</div>
                                <div class="from-line">{{ $from['phone'] }}</div>
                            @else
                                <div class="missing">Set Firm Details under Appearance</div>
                            @endif
                        </div>
                    </td>
                @endforeach

                {{-- Pad the last row so the remaining slips keep their column width. --}}
                @for ($i = $row->count(); $i < $columns; $i++)
                    <td class="slot empty"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>

</html>

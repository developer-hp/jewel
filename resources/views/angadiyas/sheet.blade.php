{{--
    A4 sheet of angadiya slips.

    Tables all the way down, and the slip's box is a table cell rather than a bordered
    <div>. That is not decoration: given a <div> with a border wrapping block children,
    mPDF paints the border around *every child*, each sized to its own text — which is
    what turned this sheet into a stack of underlined lines. A cell border it renders
    once, as a box.

    Each row avoids an internal page break so a slip is never split across sheets.
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

        html,
        body {
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
            border-collapse: collapse;
        }

        table.sheet tr {
            page-break-inside: avoid;
        }

        /* The gap between slips is padding on the slot, not border-spacing — mPDF
           handles collapsed borders far more predictably than separated ones. */
        td.slot {
            width: {{ round(100 / $columns, 4) }}%;
            vertical-align: top;
            padding: 0 1.5mm 3mm 1.5mm;
            border: none;
        }

        table.slip {
            width: 100%;
            border-collapse: collapse;
        }

        td.slipbox {
            border: 1.2pt solid #000;
            padding: 2.5mm;
            height: {{ $slipHeightMm }}mm;
            vertical-align: top;
        }

        /* Every line inside the box is borderless, explicitly. */
        .slipbox div {
            border: none;
        }

        .insurance,
        .to {
            font-weight: bold;
            font-size: 11pt;
        }

        .line {
            font-weight: bold;
            font-size: 10.5pt;
        }

        .rule {
            border-top: 0.8pt solid #000;
            font-size: 1pt;
            line-height: 1;
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
                        <table class="slip">
                            <tr>
                                <td class="slipbox">
                                    <div class="insurance">INSURANCE : {{ number_format((float) $slip->insurance_amount, 0) }}</div>
                                    <div class="to">TO : {{ $slip->city }}</div>
                                    <div class="line">{{ $slip->name }}</div>
                                    <div class="line">{{ $slip->mobile }}</div>

                                    {{-- A bordered empty block, not <hr>: mPDF gives an
                                         <hr> a margin of its own that this cannot control. --}}
                                    <div class="rule">&nbsp;</div><br><br>
                                    <hr>
                                    <div class="from-label">FROM</div>
                                    @if ($from)
                                        <div class="from-line">{{ $from['name'] }}</div>
                                        <div class="from-line">{{ $from['phone'] }}</div>
                                    @else
                                        <div class="missing">Set Firm Details under Appearance</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach

                {{-- Pad the last row so the remaining slips keep their column width. --}}
                @for ($i = $row->count(); $i < $columns; $i++)
                    <td class="slot"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>

</html>

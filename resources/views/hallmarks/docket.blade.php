{{--
    The hallmarking docket.

    Laid out entirely with tables, and every width fixed in mm: dompdf's support for
    flexbox and grid is poor, and percentage widths on nested tables stretch the
    summary boxes across the page. A4 portrait less 12mm margins gives 186mm of
    content, which is what the widths below add up to.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Hallmark {{ $hallmark->lot_no }}</title>
    <style>
        @page {
            margin: 12mm;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            /* Helvetica is a PDF core font, so nothing is embedded. It carries no
               rupee glyph, which is why the amounts print bare. */
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #000;
        }

        .head {
            width: 186mm;
            margin-bottom: 4mm;
        }

        .head td {
            padding: 0;
            vertical-align: top;
        }

        h1 {
            font-size: 15pt;
            margin: 0;
        }

        .meta {
            text-align: right;
            font-weight: bold;
            font-size: 10.5pt;
            line-height: 1.45;
            white-space: nowrap;
        }

        table.lines {
            width: 186mm;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }

        table.lines th,
        table.lines td {
            border: 0.8pt solid #000;
            padding: 1.4mm 1.5mm;
            text-align: center;
            overflow: hidden;
        }

        table.lines thead th {
            background-color: #d9d9d9;
            font-size: 9pt;
            font-weight: bold;
        }

        table.lines td.particulars,
        table.lines tfoot td {
            font-weight: bold;
        }

        /* The TOTAL row leaves the SC cell open, as on the sample docket. */
        table.lines tfoot td.blank {
            border: none;
        }

        table.summary {
            border-collapse: collapse;
        }

        table.summary th,
        table.summary td {
            border: 0.8pt solid #000;
            padding: 1.4mm 2mm;
            font-weight: bold;
            text-align: center;
        }

        .photo {
            margin-top: 6mm;
            border: 0.8pt solid #000;
            padding: 2mm;
            width: 55mm;
        }

        .photo img {
            max-height: 70mm;
            max-width: 51mm;
        }
    </style>
</head>

<body>
    <table class="head">
        <tr>
            <td><h1>Hallmark</h1></td>
            <td class="meta">
                Date: {{ $hallmark->hallmark_date->format('d/m/Y') }}<br>
                Lot No:{{ $hallmark->lot_no }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 46mm">PARTICULARS</th>
                <th style="width: 24mm">PURITY</th>
                <th style="width: 26mm">QUANTITY</th>
                <th style="width: 30mm">PCS PER QUANTITY</th>
                <th style="width: 30mm">TOTAL PCS</th>
                <th style="width: 30mm">SC</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($hallmark->lines as $line)
                <tr>
                    <td class="particulars">{{ $line->description }}</td>
                    <td>{{ $line->purity?->name }}</td>
                    <td>{{ $line->quantity }}</td>
                    <td>{{ $line->pcs_per_quantity }}</td>
                    <td>{{ $line->totalPcs() }}</td>
                    <td>{{ $line->scCode() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                <td></td>
                <td>{{ $hallmark->totalQuantity() }}</td>
                <td></td>
                <td>{{ $hallmark->totalPieces() }}</td>
                <td class="blank"></td>
            </tr>
        </tfoot>
    </table>

    {{-- Two boxes side by side. The outer table is fixed-width with fixed cells, so
         the inner tables cannot stretch to fill half the page. --}}
    <table style="width: 186mm; table-layout: fixed; border-collapse: collapse;">
        <tr>
            <td style="width: 76mm; padding: 0; vertical-align: top;">
                <table class="summary" style="width: 72mm;">
                    <tr>
                        <th style="width: 44mm;">TOTAL PCS</th>
                        <td style="width: 28mm;">{{ $hallmark->totalPieces() }}</td>
                    </tr>
                    <tr>
                        <th>COST PER PCS</th>
                        <td>{{ number_format((float) $hallmark->cost_per_piece, 0) }}</td>
                    </tr>
                    <tr>
                        <th>FINAL TOTAL</th>
                        <td>{{ number_format($hallmark->totalCost(), 0) }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 110mm; padding: 0; vertical-align: top;">
                <table class="summary" style="width: 72mm;">
                    <tr>
                        <th>GROSS WT</th>
                    </tr>
                    <tr>
                        <td>{{ number_format((float) $hallmark->gross_weight, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($photo)
        <div class="photo">
            <img src="{{ $photo }}" alt="Lot {{ $hallmark->lot_no }}">
        </div>
    @endif
</body>

</html>

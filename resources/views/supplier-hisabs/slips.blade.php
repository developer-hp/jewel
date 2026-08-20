{{--
    A4 sheet of supplier hisab slips, two across.

    Laid out entirely with <table>: dompdf's support for flexbox and grid is poor, and
    the item tag already taught us that lesson. Each row avoids an internal page break
    so a slip is never split across sheets.
--}}
@php
    $weight = fn ($v, $dp = 3) => number_format((float) $v, $dp);
    $money = fn ($v) => number_format((float) $v, 0);
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Supplier Hisab</title>
    <style>
        @page {
            margin: 8mm;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            /* Helvetica is a PDF core font, so nothing is embedded and the sheet stays
               a few KB. It carries no rupee glyph, hence the bare amounts. */
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #000;
        }

        table.sheet {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3mm;
        }

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

        table.head {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1mm;
        }

        table.head td {
            padding: 0;
            vertical-align: top;
        }

        .name {
            font-size: 13pt;
            font-weight: bold;
        }

        .meta-label {
            font-size: 8pt;
            font-weight: bold;
            padding-right: 3mm !important;
        }

        .meta-value {
            font-size: 9pt;
            font-weight: bold;
        }

        table.topay {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5mm;
        }

        table.topay td {
            padding: 0;
            font-weight: bold;
            text-align: center;
        }

        table.topay td.label {
            text-align: left;
            width: 30%;
        }

        table.lines {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        table.lines th,
        table.lines td {
            border: 0.8pt solid #000;
            padding: 0.6mm 1.2mm;
            text-align: right;
        }

        table.lines th {
            background-color: #d9d9d9;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
        }

        table.lines td.item {
            text-align: center;
        }

        table.lines tfoot td {
            background-color: #d9d9d9;
            font-weight: bold;
        }

        table.lines tfoot td.total-label {
            text-align: center;
        }

        table.lines tfoot td.blank {
            background-color: transparent;
            border: none;
        }

        /* The right-aligned working: fine owed, less fine paid, converted at the
           rate, plus the cash owed. */
        table.working {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5mm;
        }

        table.working td {
            padding: 0.2mm 1.2mm 0.2mm 0;
            text-align: right;
            font-size: 8.5pt;
        }

        table.working td.op {
            width: 8mm;
            text-align: right;
            padding-right: 0.8mm;
        }

        table.working tr.rule td {
            border-top: 0.8pt solid #000;
        }

        table.working tr.grand td {
            font-weight: bold;
        }

        table.boxes {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 2mm;
        }

        table.boxes th,
        table.boxes td {
            border: 0.8pt solid #000;
            padding: 0.8mm;
            text-align: center;
            font-weight: bold;
        }

        table.boxes th {
            background-color: #d9d9d9;
        }
    </style>
</head>

<body>
    <table class="sheet">
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $hisab)
                    <td class="slot">

                        <table class="head">
                            <tr>
                                <td rowspan="2" class="name">Name : {{ $hisab->supplier_label }}</td>
                                <td class="meta-label">DATE</td>
                                <td class="meta-value">{{ $hisab->hisab_date->format('d-m-Y') }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">RATE</td>
                                <td class="meta-value">{{ $money($hisab->ratePer10g()) }}</td>
                            </tr>
                        </table>

                        <table class="topay">
                            <tr>
                                <td class="label">TO PAY</td>
                                <td>FINE</td>
                                <td>CASH</td>
                            </tr>
                            <tr>
                                <td class="label"></td>
                                <td>{{ rtrim(rtrim($weight($hisab->fine_baki), '0'), '.') ?: '0' }}</td>
                                <td>{{ $money($hisab->cash_baki) }}</td>
                            </tr>
                        </table>

                        <table class="lines">
                            <thead>
                                <tr>
                                    <th>ITEM</th>
                                    <th>WT</th>
                                    <th>TOUCH</th>
                                    <th>FINE</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($hisab->payments as $payment)
                                    <tr>
                                        <td class="item">{{ $payment->item_name }}</td>
                                        <td>{{ rtrim(rtrim($weight($payment->gross_weight), '0'), '.') ?: '0' }}</td>
                                        <td>{{ rtrim(rtrim($weight($payment->touch), '0'), '.') ?: '0' }}</td>
                                        <td>{{ rtrim(rtrim($weight($payment->fineWeight()), '0'), '.') ?: '0' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="item">&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="total-label">TOTAL PAID</td>
                                    <td>{{ rtrim(rtrim($weight($hisab->grossPaid()), '0'), '.') ?: '0' }}</td>
                                    <td class="blank"></td>
                                    <td>{{ rtrim(rtrim($weight($hisab->fineKapi()), '0'), '.') ?: '0' }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        <table class="working">
                            <tr>
                                <td class="op"></td>
                                <td>{{ $weight($hisab->fine_baki) }}</td>
                            </tr>
                            <tr class="rule">
                                <td class="op">-</td>
                                <td>{{ $weight($hisab->fineKapi()) }}</td>
                            </tr>
                            <tr>
                                <td class="op"></td>
                                <td>{{ $weight($hisab->fineRemaining()) }}</td>
                            </tr>
                            <tr class="rule">
                                <td class="op">x</td>
                                <td>{{ $money($hisab->ratePerGram()) }}</td>
                            </tr>
                            <tr>
                                <td class="op"></td>
                                <td>{{ $money($hisab->cashForRemainingFine()) }}</td>
                            </tr>
                            <tr class="rule">
                                <td class="op">+</td>
                                <td>{{ $money($hisab->cash_baki) }}</td>
                            </tr>
                            <tr class="grand">
                                <td class="op"></td>
                                <td>{{ $money($hisab->cashApvi()) }}</td>
                            </tr>
                        </table>

                        <table class="boxes">
                            <tr>
                                <th>Gross Weight</th>
                                <th>CASH</th>
                            </tr>
                            <tr>
                                <td>{{ rtrim(rtrim($weight($hisab->grossPaid()), '0'), '.') ?: '0' }}</td>
                                <td>{{ $money($hisab->cashApvi()) }}</td>
                            </tr>
                        </table>

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

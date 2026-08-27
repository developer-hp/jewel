{{--
    The hallmarking docket.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Laid out entirely
    with tables and every width fixed in mm: dompdf's support for flexbox and grid is
    poor, and percentage widths on nested tables stretch the summary boxes across the
    page. A4 portrait less the layout's 15mm margins gives 180mm of content, which is
    what the widths below add up to.
--}}
@extends('layouts.pdf')

@section('styles')
    <style>
        body {
            font-size: 13px;
        }

        table.head {
            width: 180mm;
            margin-bottom: 4mm;
        }

        h1 {
            font-size: 20px;
            margin: 0;
        }

        .meta {
            line-height: 1.45;
            white-space: nowrap;
        }

        table.lines {
            width: 180mm;
            table-layout: fixed;
        }

        table.lines td {
            overflow: hidden;
        }

        /* The two summary boxes sit in the cells of an invisible outer table.

           That outer table must NOT use pdf.css's .no-border, whose rule is
           `.no-border th, .no-border td` — a descendant selector, so it reaches
           straight through into the nested summary tables and strips their borders
           and header shading too. Hence a local class that only unborders the
           slot cells themselves. */
        .boxes {
            width: 180mm;
            table-layout: fixed;
            border-collapse: collapse;
        }

        td.slot {
            border: none;
            vertical-align: top;
            padding: 0 6mm 0 0;
        }

        /* Fixed width beats .pdf-table's width:100%, so the box cannot stretch to
           fill its half of the page. Both boxes are the same size and their label
           columns line up across the gap. */
        table.summary {
            width: 78mm;
        }

        table.summary th {
            width: 48mm;
        }

        table.summary td {
            width: 30mm;
        }

        .photo {
            margin-top: 6mm;
            border: 1px solid;
            padding: 2mm;
            width: 55mm;
        }

        .photo img {
            max-height: 70mm;
            max-width: 51mm;
        }
    </style>
@endsection

@section('content')
    <table class="pdf-table no-border head">
        <tr>
            <td class="no-border text-left"><h1>Hallmark</h1></td>
            <td class="no-border text-right text-bold font14 meta">
                Date: {{ $hallmark->hallmark_date->format('d-m-Y') }}<br>
                Lot No: {{ $hallmark->lot_no }}
            </td>
        </tr>
    </table>

    <table class="pdf-table pd2 lines font12">
        <thead>
            <tr>
                <th style="width: 48mm" class="text-center">PARTICULARS</th>
                <th style="width: 24mm" class="text-center">PURITY</th>
                <th style="width: 24mm" class="text-center">QUANTITY</th>
                <th style="width: 30mm" class="text-center">PCS PER QUANTITY</th>
                <th style="width: 28mm" class="text-center">TOTAL PCS</th>
                <th style="width: 26mm" class="text-center">SC</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($hallmark->lines as $line)
                <tr>
                    <td class="text-center text-bold">{{ $line->description }}</td>
                    <td class="text-center">{{ $line->purity?->name }}</td>
                    <td class="text-center">{{ $line->quantity }}</td>
                    <td class="text-center">{{ $line->pcs_per_quantity }}</td>
                    <td class="text-center">{{ $line->totalPcs() }}</td>
                    <td class="text-center">{{ $line->scCode() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="background-light">
                <td class="text-center text-bold">TOTAL</td>
                <td></td>
                <td class="text-center text-bold">{{ $hallmark->totalQuantity() }}</td>
                <td></td>
                <td class="text-center text-bold">{{ $hallmark->totalPieces() }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Two boxes side by side, each a label/value grid. The outer table is
         fixed-width with fixed cells, so the inner tables cannot stretch. --}}
    <table class="boxes" style="margin-top: 5mm;">
        <tr>
            <td class="slot" style="width: 90mm;">
                <table class="pdf-table pd2 font13 summary">
                    <tr>
                        <th class="text-center">TOTAL PCS</th>
                        <td class="text-center text-bold">{{ $hallmark->totalPieces() }}</td>
                    </tr>
                    <tr>
                        <th class="text-center">COST PER PCS</th>
                        <td class="text-center text-bold">{{ number_format((float) $hallmark->cost_per_piece, 0) }}</td>
                    </tr>
                    <tr>
                        <th class="text-center">FINAL TOTAL</th>
                        <td class="text-center text-bold">{{ number_format($hallmark->totalCost(), 0) }}</td>
                    </tr>
                </table>
            </td>
            <td class="slot" style="width: 90mm;">
                <table class="pdf-table pd2 font13 summary">
                    <tr>
                        <th class="text-center">GROSS WT</th>
                        <td class="text-center text-bold">{{ number_format((float) $hallmark->gross_weight, 2) }}</td>
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
@endsection

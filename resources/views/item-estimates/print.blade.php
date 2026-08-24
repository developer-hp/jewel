{{--
    The jadtar estimate: a customer copy above a FOR OFFICE USE copy, with the stone
    breakdown under each item and the totals box at the right.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only —
    dompdf's support for flexbox and grid is poor.

    An attached OG estimate prints as a further page, through that module's own
    document partial rather than a second copy of its markup.
--}}
@extends('layouts.pdf')

@php
    $rowsPerCopy = 8;
    $wt = fn ($v) => number_format((float) $v, 3);
    $money = fn ($v) => number_format((float) $v, 0);
@endphp

@section('styles')
    <style>
        body {
            font-size: 11px;
        }

        .form-page {
            page-break-after: always;
        }

        .form-page.last {
            page-break-after: auto;
        }

        .copy-title {
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2mm;
            margin-top: 2mm;
        }

        table.lines {
            width: 100%;
            table-layout: fixed;
            margin-top: 2mm;
        }

        table.lines td {
            vertical-align: top;
        }

        /* pdf.css kills borders with a DESCENDANT selector — `.no-border th, td` —
           so the plain layout table these sit inside strips the rules off them too.
           Put them back explicitly; without this the whole document prints flat. */
        table.lines > thead > tr > th,
        table.lines > tbody > tr > td,
        table.lines > tfoot > tr > td,
        table.summary th,
        table.summary td {
            border: 0.8pt solid #000 !important;
        }

        table.lines > thead > tr > th {
            background: #cdcdcd !important;
        }

        /* The stone breakdown sits inside the item cell, small and borderless.

           Width is auto, not 100%: stretched across the item column the four
           figures drifted apart into a sparse grid. Sized to its content it reads
           as one compact block, and the padding below is what separates the
           columns — without it the pieces and the rate ran together as "7 92,000". */
        table.breakdown {
            width: auto;
            margin-top: 0.5mm;
        }

        table.breakdown td {
            border: none !important;
            font-size: 9px;
            font-weight: bold;
            color: #444;
            padding: 0 0 0 4mm !important;
            margin: 0mm 0mm !important;
            white-space: nowrap;
        }

        /* The name leads the row, so nothing sits to its left. */
        table.breakdown td.name {
            padding-left: 0 !important;
        }

        .photo img {
            max-width: 16mm;
            max-height: 16mm;
        }

        table.summary th {
            background: #efefef !important;
            text-align: left;
        }

        table.summary th,
        table.summary td {
            padding: 1mm 2mm;
            font-weight: bold;
        }
    </style>

    {{-- The attached OG page needs that document's own rules. --}}
    <style>
        @include('og-estimates.partials._styles')
    </style>
@endsection

@section('content')
    @foreach ($estimates as $estimate)
        @php($totals = $estimate->totals())
        @php($summary = $estimate->summary())
        @php($og = $estimate->ogEstimate)

        {{-- Break only when a page follows: the attached OG document, or another
             estimate. Otherwise the document ends with a blank sheet. --}}
        <div class="form-page @if ($loop->last && ! $og) last @endif">

            @foreach (['customer', 'office'] as $copy)
                <div class="copy-title">{{ $copy === 'office' ? 'FOR OFFICE USE' : 'ROUGH ESTIMATE' }}</div>

                <table class="pdf-table no-border" style="width: 100%; margin-bottom: 1mm;">
                    <tr>
                        <td class="no-border" style="width: 52%;">
                            <span class="text-bold font13">Name</span>&nbsp;&nbsp;&nbsp;{{ $estimate->customer_name }}
                            @if ($copy === 'office' && $estimate->address)
                                <br><span class="text-bold font13">Address</span>&nbsp;&nbsp;{{ $estimate->address }}
                            @endif
                        </td>
                        <td class="no-border" style="width: 28%;">
                            <span class="text-bold font13">Mobile</span>&nbsp;&nbsp;{{ $estimate->contact_no }}
                        </td>
                        <td class="no-border text-right" style="width: 20%;">
                            @if ($copy === 'office')
                                <span class="text-bold font10">{{ $estimate->sales_person_name }}</span><br>
                                <span class="text-bold">Ref No:{{ $estimate->reference() }}</span><br>
                            @endif
                            <span class="text-bold font14">{{ $estimate->estimate_date->format('d-m-Y') }}</span>
                        </td>
                    </tr>
                </table>

                <table class="pdf-table no-border" style="width: 100%;">
                    <tr>
                        <td class="no-border" style="width: 80%; vertical-align: top;">
                            <table class="pdf-table pd2 lines font11">
                                <thead>
                                    <tr>
                                        <th style="width: 5%" class="text-center">#</th>
                                        <th class="text-center">ITEM</th>
                                        <th style="width: 10%" class="text-center">GROSS</th>
                                        <th style="width: 10%" class="text-center">NET WT</th>
                                        <th style="width: 13%" class="text-center">RATE</th>
                                        <th style="width: 14%" class="text-center">LC</th>
                                        <th style="width: 15%" class="text-center">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($estimate->lines as $i => $line)
                                        <tr>
                                            <td class="text-center">
                                                {{-- Photos are opt-in; off, the row simply carries the number. --}}
                                                @if ($estimate->show_photo && $line->item?->photoDataUri())
                                                    <div class="photo"><img src="{{ $line->item->photoDataUri() }}" alt=""></div>
                                                @else
                                                    {{ $i + 1 }}
                                                @endif
                                            </td>
                                            <td class="text-left">
                                                <span class="text-bold">
                                                    @if ($copy === 'office' && $line->item)
                                                        {{ $line->item->code }}
                                                    @endif
                                                    {{ $line->description }}
                                                </span>

                                                @if ($line->stones->isNotEmpty() || (float) $line->oc_amount > 0)
                                                    <table class="breakdown">
                                                        @foreach ($line->stones as $stone)
                                                            <tr>
                                                                <td class="name">{{ $stone->stoneMaster?->name }}</td>
                                                                <td class="text-right">
                                                                    {{ (float) $stone->weight_grams > 0 ? $wt($stone->weight_grams) : '' }}
                                                                </td>
                                                                <td class="text-right">{{ $stone->pieces ?: '' }}</td>
                                                                <td class="text-right">{{ $money($stone->rate) }}</td>
                                                                <td class="text-right">{{ $money($stone->amount) }}</td>
                                                            </tr>
                                                        @endforeach

                                                        {{-- Other charges belong in this list too, or the
                                                             breakdown does not add up to the line total. --}}
                                                        @if ((float) $line->oc_amount > 0)
                                                            <tr>
                                                                <td class="name">OC</td>
                                                                <td></td>
                                                                <td></td>
                                                                <td></td>
                                                                <td class="text-right">{{ $money($line->oc_amount) }}</td>
                                                            </tr>
                                                        @endif
                                                    </table>
                                                @endif
                                            </td>
                                            <td class="text-right">{{ $wt($line->gross_weight) }}</td>
                                            <td class="text-right">{{ $wt($line->netWeight()) }}</td>
                                            <td class="text-right">{{ $money($line->rate) }}</td>
                                            <td class="text-right">{{ $line->labourLabel() }}</td>
                                            <td class="text-right">{{ $money($line->total()) }}</td>
                                        </tr>
                                    @endforeach

                                    {{-- Padded out, so there is room to write more in by hand. --}}
                                    @for ($i = $estimate->lines->count(); $i < $rowsPerCopy; $i++)
                                        <tr>
                                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="text-center text-bold">
                                            TOTAL : {{ $money($totals->charges) }}
                                        </td>
                                        <td class="text-right text-bold">{{ $wt($totals->gross) }}</td>
                                        <td class="text-right text-bold">{{ $wt($totals->net) }}</td>
                                        <td class="text-right text-bold">{{ $money($totals->metal) }}</td>
                                        <td class="text-right text-bold">{{ $money($totals->labour) }}</td>
                                        <td class="text-right text-bold">{{ $money($totals->total) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </td>

                        <td class="no-border" style="width: 15%; vertical-align: top; padding-left: 3mm !important;">
                            <table class="pdf-table pd2 summary font11">
                                <tr>
                                    <th class="text-left">Amount</th>
                                    <td class="text-right">{{ $money($summary->amount) }}</td>
                                </tr>
                                @if ($estimate->gst_enabled)
                                    <tr>
                                        <th class="text-left">GST</th>
                                        <td class="text-right">{{ $money($summary->gst) }}</td>
                                    </tr>
                                @endif
                                @if ((float) $summary->round_off !== 0.0)
                                    <tr>
                                        <th class="text-left">Round</th>
                                        <td class="text-right">{{ number_format($summary->round_off, 0) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th class="text-left">TOTAL</th>
                                    <td class="text-right">{{ $money($summary->total) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                @if ($copy === 'customer')
                    <div style="height: 5mm;"></div>
                @endif
            @endforeach

        </div>

        {{-- The old gold coming in against this purchase, on its own page. --}}
        @if ($og)
            @include('og-estimates.partials._document', [
                'estimate' => $og,
                'rowsPerCopy' => 10,
                'last' => $loop->last,
            ])
        @endif
    @endforeach
@endsection

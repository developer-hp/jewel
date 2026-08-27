{{--
    The repair form, printed twice per sheet: the customer copy on the left and the
    office copy on the right.

    Landscape, so the two copies sit side by side at a readable width: A4 landscape
    less the layout's 15mm margins gives 267mm of content, ~130mm per copy.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only —
    dompdf's support for flexbox and grid is poor, and the item tag already taught
    us that lesson.
--}}
@extends('layouts.pdf')

@php
    $weight = fn ($v) => $v === null ? '' : number_format((float) $v, 3);
@endphp

@section('styles')
    <style>
        body {
            font-size: 15px;
        }

        /* Each form is a page of its own — one repair, one sheet. This was an empty
           stub, so a two-form print ran both down the same page. */

        table.sheet {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3mm 0;
        }

        table.sheet tr {
            page-break-inside: avoid;
        }

        /* The slot's own border (from pdf.css's `th,td`) is the frame around each
           copy. Fixed height so the pair fills the sheet instead of huddling at the
           top: A4 landscape less the layout's 15mm margins leaves ~180mm. */
        td.slot {
            width: 50%;
            height: 176mm;
            vertical-align: top;
            padding: 0;
        }

        

        .title {
            font-size: 24px;
            font-weight: bold;
            line-height: 1.1;
        }

        

        /* Right-aligned so the reference block hugs the edge of the copy rather than
           starting at the 55% mark and leaving a band of dead paper beside it. */
        .refbox {
            padding: 1.5mm 0 1.5mm 1.5mm;
            line-height: 1.5;
        }

        table.lines {
            margin-top: 2mm;
        }

        /* Blank ruled rows pad the table out to a usable depth. The counter writes
           on this form, so the empty space is the point — and it is what keeps the
           terms block anchored near the foot of the copy. */
        table.lines tbody tr.filler td {
            height: 8mm;
        }

        /* The whole reason this was asked for: the terms ran flush against the frame
           on every side. */
        .terms {
            margin-top: 3mm;
            padding: 2.5mm 3mm;
        }

        .terms .head {
            font-weight: bold;
            margin-bottom: 1.5mm;
        }

        .terms .line {
            font-size: 13px;
            line-height: 1.6;
        }
    </style>
@endsection

@section('content')
    @foreach ($forms as $form)
        {{-- Resolved once per form: both copies show the same picture, and there is
             no sense base64-encoding the upload twice. --}}
        @php($photo = $form->photoDataUri())

        <table class="sheet form-page @if ($loop->last) last @endif">
            <tr>
                @foreach (['customer', 'office'] as $copy)
                    <td class="slot">
                        <div class="copy">

                            @if ($copy === 'office')
                                <div class="office-band text-right text-bold">For Office Use</div>
                            @else
                                <div class="office-band text-right text-bold">&nbsp;</div>
                            @endif

                            <table class="pdf-table no-border" style="width: 100%;">
                                <tr>
                                    <td class="no-border title">REPAIRING FORM</td>
                                    <td class="no-border text-right">
                                        <span class="text-bold font16">
                                            {{ $copy === 'office' ? $firm['office_phone'] : $firm['phone'] }}
                                        </span>
                                        @if ($firm['website'])
                                            <br><span class="text-bold font10">{{ $firm['website'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <table class="pdf-table no-border" style="width: 100%; margin-top: 2mm;">
                                <tr>
                                    <td class="no-border" style="width: 60%; vertical-align: bottom;">
                                        <div class="text-bold font16">M/S. : {{ $form->customer_name }}</div>
                                        <div class="text-bold font16">Contact No. : {{ $form->contact_no }}</div>
                                        @if ($copy === 'office')
                                            @foreach ($form->salesPersons as $person)
                                                <div class="text-bold font16">Sales Person: {{ $person->name }}</div>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td class="no-border text-left" style="width: 25%;">
                                        <div class="refbox text-bold font16">
                                            R. No. : {{ $form->reference() }}<br>
                                            Date : {{ $form->form_date->format('d-m-Y') }}<br>
                                            Delivery Date : {{ $form->delivery_date->format('d-m-Y') }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <table class="pdf-table pd5 lines font15">
                                <thead>
                                    <tr>
                                        <th style="width: 12%" class="text-center">No.</th>
                                        <th class="text-center">PARTICULARS</th>
                                        <th style="width: 22%" class="text-center">ARTICLE WEIGHT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($form->lines as $i => $line)
                                        <tr>
                                            <td class="text-center">{{ $i + 1 }}</td>
                                            <td class="text-left">{{ $line->description }}</td>
                                            <td class="text-right">{{ $weight($line->net_weight) }}</td>
                                        </tr>
                                    @endforeach

                                    {{-- Ruled space to write in, and what stops a
                                         one-line repair leaving the copy two thirds
                                         empty. --}}
                                    @for ($i = $form->lines->count(); $i < 6; $i++)
                                        <tr class="filler">
                                            <td>&nbsp;</td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-left text-bold">
                                            Remarks : {{ $form->remarks }}
                                        </td>
                                    </tr>
                                    @if ($form->approx_extra_charge !== null)
                                        <tr>
                                            <td colspan="2" class="text-left text-bold"><span class="text-red">Approx Extra Charge</span></td>
                                            <td class="text-right text-bold">
                                                <span class="text-red">{{ number_format((float) $form->approx_extra_charge, 0) }}</span>
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>

                            <table class="pdf-table no-border">
                                <tr>
                                    <td class="no-border mb5">
                                        @if ($terms !== [])
                                            <div class="terms">
                                                <div class="text-bold">Terms and Conditions:</div>
                                                @foreach ($terms as $term)
                                                    <div class="line">{{ $term }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    @if ($photo)
                                        <td class="no-border">
                                            <img src="{{ $photo }}" style="height:100px;" alt="">
                                        </td>
                                    @endif
                                </tr>
                            </table>

                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endforeach
@endsection

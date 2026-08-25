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
        /* Each form is a page of its own — one repair, one sheet. */
        
        
        table.sheet {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3mm 0;
        }

        table.sheet tr {
            page-break-inside: avoid;
        }

        td.slot {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        .copy {
            padding: 2mm;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
        }

        .office-band {
            padding: 1mm;
            margin-bottom: 1.5mm;
        }

        .refbox {
            padding: 1.5mm;
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
                                <div class="office-band text-center text-bold">For Office Use</div>
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
                                    <td class="no-border" style="width: 55%; vertical-align: bottom;">
                                        <div class="text-bold font16">M/S. : {{ $form->customer_name }}</div>
                                        <div class="text-bold font16">Contact No. : {{ $form->contact_no }}</div>
                                        @if ($copy === 'office')
                                            @foreach ($form->salesPersons as $person)
                                                <div class="text-bold font16">Sales Person: {{ $person->name }}</div>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td class="no-border" style="width: 45%;">
                                        <div class="refbox text-bold font16">
                                            R. No. : {{ $form->reference() }}<br>
                                            Date : {{ $form->form_date->format('d-m-Y') }}<br>
                                            Delivery Date :<br>{{ $form->delivery_date->format('d-m-Y') }}
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
                                                <div class="head">Terms and Conditions:</div>
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

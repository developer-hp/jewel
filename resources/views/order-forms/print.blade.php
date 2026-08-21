{{--
    The order form the customer takes away and brings back at delivery.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only —
    dompdf's support for flexbox and grid is poor. The lines table is padded out to
    ten rows as on the paper original, so there is room to write more in by hand.
--}}
@extends('layouts.pdf')

@php
    $rowsPerForm = 10;
    $weight = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '';
    $money = fn ($v) => (float) $v > 0 ? number_format((float) $v, 0) : '0';
@endphp

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        .form-page {
            page-break-after: always;
        }

        .form-page.last {
            page-break-after: auto;
        }

        .title {
            font-size: 26px;
            font-weight: bold;
            line-height: 1.1;
        }

        .refbox {
            border: 1px solid;
            padding: 2mm;
        }

        table.lines {
            width: 100%;
            table-layout: fixed;
            margin-top: 3mm;
        }

        table.lines td {
            height: 6mm;
        }

        .bring {
            border: 1px solid;
            border-top: none;
            padding: 1.2mm;
            text-align: center;
            font-weight: bold;
        }

        .terms .head {
            font-weight: bold;
            font-size: 13px;
        }

        .terms .line {
            font-weight: bold;
            font-size: 9px;
        }

        .signbox {
            border: 1px solid;
            height: 22mm;
            text-align: center;
            vertical-align: bottom;
        }
    </style>
@endsection

@section('content')
    @foreach ($forms as $form)
        <div class="form-page @if ($loop->last) last @endif">

            <table class="pdf-table no-border" style="width: 100%;">
                <tr>
                    <td class="no-border title" style="width: 45%; vertical-align: top;">ORDER FORM</td>
                    <td class="no-border text-right" style="vertical-align: top;">
                        <span class="text-bold font15">For Query : {{ $firm['query_phone'] }}</span>
                        @if ($firm['website'])
                            <br><span class="text-bold font11">{{ $firm['website'] }}</span>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="pdf-table no-border" style="width: 100%; margin-top: 2mm;">
                <tr>
                    <td class="no-border" style="width: 58%; vertical-align: bottom;">
                        <div class="text-bold font16">M/S. : {{ $form->customer_name }}</div>
                        <div class="text-bold font16">Contact No. : {{ $form->contact_no }}</div>
                    </td>
                    <td class="no-border" style="width: 42%;">
                        <div class="refbox text-bold font13">
                            Order No. : {{ $form->reference() }}<br>
                            Date : {{ $form->form_date->format('d-m-Y') }}<br>
                            Delivery Date : {{ $form->delivery_date->format('d-m-Y') }}
                        </div>
                    </td>
                </tr>
            </table>

            <table class="pdf-table pd2 lines font11">
                <thead>
                    <tr>
                        <th style="width: 9%" class="text-center">No.</th>
                        <th class="text-center">Particulars</th>
                        <th style="width: 11%" class="text-center">Pcs Size</th>
                        <th style="width: 14%" class="text-center">Approx Net Weight</th>
                        <th style="width: 14%" class="text-center">Labour per gm</th>
                        <th style="width: 13%" class="text-center">Approx O.C.</th>
                        <th style="width: 13%" class="text-center">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($form->lines as $i => $line)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td class="text-left">{{ $line->description }}</td>
                            <td class="text-center">{{ $line->size_pcs }}</td>
                            <td class="text-right">{{ $weight($line->net_weight) }}</td>
                            <td class="text-right">{{ $line->labourLabel() }}</td>
                            <td class="text-right">{{ $money($line->oc_amount) }}</td>
                            <td class="text-right">{{ $line->rateLabel() }}</td>
                        </tr>
                    @endforeach

                    {{-- Padded to ten, as on the paper original. --}}
                    @for ($i = $form->lines->count(); $i < $rowsPerForm; $i++)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-center text-bold">Total Netweight</td>
                        <td class="text-right text-bold">{{ $weight($form->totalNetWeight()) }}</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="text-left text-bold">Remarks : {{ $form->remarks }}</td>
                    </tr>
                </tfoot>
            </table>

            <div class="bring">PLEASE BRING THIS ORDER FORM ALONG WITH YOU DURING DELIVERY</div>

            <table class="pdf-table no-border" style="width: 100%; margin-top: 3mm;">
                <tr>
                    <td class="no-border" style="width: 52%; vertical-align: top;">
                        @if ($terms !== [])
                            <div class="terms">
                                <div class="head">Terms and Conditions:</div>
                                @foreach ($terms as $term)
                                    <div class="line">{{ $term }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="no-border signbox" style="width: 24%;">Approved By<br>Customer</td>
                    <td class="no-border" style="width: 2%;">&nbsp;</td>
                    <td class="no-border signbox" style="width: 22%;">&nbsp;</td>
                </tr>
            </table>

        </div>
    @endforeach
@endsection

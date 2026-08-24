{{--
    The voucher: a customer copy above a FOR OFFICE USE copy, then the signature box.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only —
    dompdf's support for flexbox and grid is poor.
--}}
@extends('layouts.pdf')

@php($money = fn ($v) => number_format((float) $v, 0))

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

        .copy-title {
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2mm;
        }

        table.lines {
            width: 100%;
            table-layout: fixed;
            margin-top: 2mm;
        }

        table.lines td.detail {
            height: 34mm;
            vertical-align: top;
        }

        .words {
            font-weight: bold;
            margin-top: 2mm;
        }

        .signbox {
            border: 1px solid;
            width: 55mm;
            height: 20mm;
            text-align: center;
            vertical-align: top;
            font-weight: bold;
        }
    </style>
@endsection

@section('content')
    @foreach ($vouchers as $voucher)
        @php($rates = $voucher->rateBlock())

        <div class="form-page @if ($loop->last) last @endif">

            @foreach (['customer', 'office'] as $copy)
                <div class="copy-title">{{ $copy === 'office' ? 'FOR OFFICE USE' : 'VOUCHER' }}</div>

                <table class="pdf-table no-border" style="width: 100%; margin-bottom: 1mm;">
                    <tr>
                        <td class="no-border">
                            @if ($copy === 'office')
                                <span class="text-bold font10">{{ $voucher->sales_person_name }}</span>
                            @endif
                        </td>
                        <td class="no-border text-right">
                            @if ($copy === 'office')
                                <span class="text-bold">Ref No:{{ $voucher->reference() }}</span><br>
                            @endif
                            <span class="text-bold font14">{{ $voucher->voucher_date->format('d-m-Y') }}</span>
                        </td>
                    </tr>
                </table>

                <table class="pdf-table pd2 lines font11">
                    <thead>
                        <tr>
                            <th class="text-center">DESCRIPTION</th>
                            <th style="width: 24%" class="text-center">PAYMENT MODE</th>
                            <th style="width: 20%" class="text-center">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="detail text-left">
                                {{ $voucher->description }}

                                @if ($voucher->isAgainstOrder() && $voucher->orderForm)
                                    <br>{{ $voucher->orderForm->reference() }}
                                    <br>Name : {{ $voucher->orderForm->customer_name }}
                                    <br>Mobile : {{ $voucher->orderForm->contact_no }}
                                @endif

                                {{-- Taken from the order this is against: purities it has
                                     pinned print with their rate, and it reads fixed. --}}
                                @if ($rates)
                                    <br>Rate : {{ $rates['fixed'] ? 'fixed' : 'open' }}
                                    @foreach ($rates['rows'] as $row)
                                        <br>{{ $row['label'] }} : {{ $row['rate'] ? number_format($row['rate'], 2) : '' }}
                                    @endforeach
                                @endif
                            </td>
                            <td class="text-center" style="vertical-align: top;">{{ $voucher->modeLabel() }}</td>
                            <td class="text-right" style="vertical-align: top;">{{ $money($voucher->amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="text-left text-bold">TOTAL</td>
                            <td>&nbsp;</td>
                            <td class="text-right text-bold">{{ $money($voucher->amount) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="words">{{ $voucher->amountInWords() }}</div>

                @if ($copy === 'customer')
                    <div style="height: 6mm;"></div>
                @endif
            @endforeach

            <table class="pdf-table no-border" style="width: 100%; margin-top: 6mm;">
                <tr>
                    <td class="no-border">&nbsp;</td>
                    <td class="signbox">Authorised Signature</td>
                </tr>
            </table>

        </div>
    @endforeach
@endsection

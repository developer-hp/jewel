{{--
    The karigar receipt, printed twice per order: the first copy travels with the
    goods, the second stays in the office and carries the IN/OUT grid and the QR
    that closes the order when scanned.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only —
    dompdf's support for flexbox and grid is poor.
--}}
@extends('layouts.pdf')

@php
    $weight = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.').' grm';
@endphp

@section('styles')
    <style>
        body {
            font-size: 13px;
        }

        .order-page {
            page-break-after: always;
        }

        .order-page.last {
            page-break-after: auto;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .copy {
            margin-bottom: 8mm;
        }

        table.receipt {
            width: 100%;
            table-layout: fixed;
        }

        table.receipt th {
            width: 38%;
            text-align: center;
        }

        table.receipt td {
            text-align: center;
        }

        .photo-box {
            height: 26mm;
            text-align: center;
            vertical-align: middle;
        }

        .photo-box img {
            max-height: 24mm;
            max-width: 60mm;
        }

        .qr img {
            width: {{ $qrMm }}mm;
            height: {{ $qrMm }}mm;
        }

        table.inout {
            width: 100%;
            table-layout: fixed;
        }

        table.inout th {
            width: 50%;
            text-align: center;
        }

        table.inout td {
            height: 12mm;
        }
    </style>
@endsection

@section('content')
    @foreach ($orders as $order)
        <div class="order-page @if ($loop->last) last @endif">

            @foreach (['customer', 'office'] as $copy)
                <div class="copy">

                    <table class="pdf-table no-border" style="width: 100%;">
                        <tr>
                            <td class="no-border title">KARIGAR RECEIPT</td>
                            <td class="no-border text-right text-bold font13">{{ $header }}</td>
                        </tr>
                    </table>

                    <table class="pdf-table pd3 receipt font13" style="margin-top: 2mm;">
                        <tr>
                            <th>FORM NO</th>
                            <td class="text-bold">{{ $order->form_no }}</td>
                        </tr>
                        <tr>
                            <th>DATE</th>
                            <td>{{ $order->order_date->format('d-m-Y') }}</td>
                        </tr>
                        <tr>
                            <th>SUPPLIER</th>
                            <td>{{ $order->supplier_name }}</td>
                        </tr>
                        <tr>
                            <th>ITEM CODE</th>
                            <td>{{ $order->order_type_name }}</td>
                        </tr>
                        <tr>
                            <th>DESCRIPTION</th>
                            <td>{{ $order->description }}</td>
                        </tr>
                        <tr>
                            <th>SAMPLE WEIGHT</th>
                            <td>{{ $weight($order->sample_weight) }}</td>
                        </tr>
                        <tr>
                            <th>DELIVERY DATE</th>
                            <td>{{ $order->customer_delivery_date->format('d-m-Y') }}</td>
                        </tr>

                        @if ($copy === 'customer')
                            <tr>
                                <td colspan="2" class="photo-box">
                                    @if ($photos[$order->id] ?? null)
                                        <img src="{{ $photos[$order->id] }}" alt="">
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                            </tr>
                        @else
                            {{-- The office copy: the photo beside the QR that closes this
                                 order, and the IN/OUT grid the bench writes on. --}}
                            <tr>
                                <td class="photo-box">
                                    @if ($photos[$order->id] ?? null)
                                        <img src="{{ $photos[$order->id] }}" alt="">
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                                <td class="no-border" style="padding: 0 !important;">
                                    <table class="pdf-table no-border" style="width: 100%;">
                                        <tr>
                                            <td class="no-border text-center qr" style="padding: 1mm !important;">
                                                <img src="{{ $qrCodes[$order->id] }}" alt="">
                                            </td>
                                        </tr>
                                    </table>
                                    <table class="pdf-table pd2 inout font13">
                                        <tr>
                                            <th>IN</th>
                                            <th>OUT</th>
                                        </tr>
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    </table>

                </div>
            @endforeach

        </div>
    @endforeach
@endsection

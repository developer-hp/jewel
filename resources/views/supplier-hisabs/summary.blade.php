{{--
    The day summary: one row per hisab, with the day's totals underneath.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only —
    dompdf's support for flexbox and grid is poor.
--}}
@extends('layouts.pdf')

@php
    $weight = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    $money = fn ($v) => number_format((float) $v, 0);
@endphp

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        .pdf-table {
            width: 100%;
            table-layout: fixed;
        }

        .supplier-col {
            width: 25%;
        }
    </style>
@endsection

@section('content')
    <table class="pdf-table no-border">
        <tr>
            <td class="no-border text-right text-bold font16">{{ $date->format('d-m-Y') }}</td>
        </tr>
    </table>

    @if ($hisabs->isEmpty())
        <p class="font13">No hisab entries on this date.</p>
    @else
        <table class="pdf-table pd3 font13">
            <thead>
                <tr>
                    <th class="supplier-col text-center">Supplier</th>
                    <th class="text-center">Fine Baki</th>
                    <th class="text-center">Cash Baki</th>
                    <th class="text-center">Fine Kapi</th>
                    <th class="text-center">Cash Apvi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hisabs as $hisab)
                    <tr>
                        <td class="text-left">{{ $hisab->supplier_label }}</td>
                        <td class="text-right">{{ $weight($hisab->fine_baki) }}</td>
                        <td class="text-right">{{ $money($hisab->cash_baki) }}</td>
                        <td class="text-right">{{ $weight($hisab->fineKapi()) }}</td>
                        <td class="text-right">{{ $money($hisab->cashApvi()) }}</td>
                    </tr>
                @endforeach
                <tr class="background-light">
                    <td class="text-center text-bold">Total</td>
                    <td class="text-right text-bold">{{ $weight($totals['fine_baki']) }}</td>
                    <td class="text-right text-bold">{{ $money($totals['cash_baki']) }}</td>
                    <td class="text-right text-bold">{{ $weight($totals['fine_kapi']) }}</td>
                    <td class="text-right text-bold">{{ $money($totals['cash_apvi']) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection

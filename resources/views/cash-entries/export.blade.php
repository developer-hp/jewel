{{--
    The cash ledger, as it is read off the counter.

    Every figure comes off the entry's own snapshots, so this neither joins to the
    documents nor recomputes anything — what it prints is what was counted.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only.
--}}
@extends('layouts.pdf')

@php
    $money = fn ($v) => number_format((float) $v, 0).' RS';
    $weight = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 1mm 0;
        }

        .meta {
            text-align: right;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3mm;
        }

        table.ledger {
            width: 100%;
            table-layout: fixed;
        }
    </style>
@endsection

@section('content')
    <h1>Cash Management</h1>

    <div class="meta">Date: {{ now()->format('d-m-Y') }}</div>

    <table class="pdf-table pd2 ledger font11">
        <thead>
            <tr>
                <th style="width: 8%" class="text-center">Ref</th>
                <th style="width: 15%" class="text-center">Location</th>
                <th style="width: 14%" class="text-center">IN</th>
                <th style="width: 14%" class="text-center">Out</th>
                <th style="width: 14%" class="text-center">Discount</th>
                <th style="width: 12%" class="text-center">Gold</th>
                <th style="width: 12%" class="text-center">Reference</th>
                <th class="text-center">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                @php($settled = $entry->settledAmount())
                <tr>
                    <td class="text-center">{{ $entry->ref_no }}</td>
                    <td class="text-left">{{ $entry->drawer?->name }}</td>
                    <td class="text-right">
                        {{ $entry->cash_event === \App\Models\CashEntry::EVENT_IN ? $money($settled) : '' }}
                    </td>
                    <td class="text-right">
                        {{ $entry->cash_event === \App\Models\CashEntry::EVENT_OUT ? $money($settled) : '' }}
                    </td>
                    <td class="text-right">{{ $money($entry->discount()) }}</td>
                    <td class="text-right">
                        {{ (float) $entry->gold_weight > 0 ? $weight($entry->gold_weight) . ' grm' : '' }}
                    </td>
                    <td class="text-center">{{ $entry->document_reference }}</td>
                    <td class="text-center">{{ $entry->entry_date->format('d-m-Y') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-center text-bold">TOTAL</td>
                <td class="text-right text-bold">{{ number_format($totals->in, 0) }}</td>
                <td class="text-right text-bold">{{ number_format($totals->out, 0) }}</td>
                <td>&nbsp;</td>
                <td class="text-right text-bold">
                    {{ $totals->gold > 0 ? $weight($totals->gold) . ' grm' : '' }}
                </td>
                <td colspan="2">&nbsp;</td>
            </tr>
        </tfoot>
    </table>

    <table class="pdf-table pd2 font12 mt10" style="width: 60%;">
        <tr>
            <th class="text-left">In the tills</th>
            <td class="text-right text-bold">{{ $money($position->cash) }}</td>
        </tr>
        <tr>
            <th class="text-left">Gold across the counter</th>
            <td class="text-right text-bold">{{ $weight($position->gold) }} grm</td>
        </tr>
    </table>
@endsection

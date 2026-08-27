{{--
    The daily stock sheet. Landscape: nine columns do not read at portrait width.
--}}
@extends('layouts.pdf')

@php
    $wt = fn ($v) => number_format((float) $v, 3);
    $pcs = fn ($v) => number_format((int) $v);
@endphp

@section('styles')
    <style>
        body { font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 1mm 0; }
        .meta { text-align: right; font-weight: bold; font-size: 13px; margin-bottom: 3mm; }
        table.sheet { width: 100%; table-layout: fixed; }
    </style>
@endsection

@section('content')
    <h1>Daily Stock Report</h1>
    <div class="meta">
        {{ $metalTypeName ?? 'All metal types' }} &nbsp;|&nbsp; Date: {{ $date->format('d-m-Y') }}
    </div>

    <table class="pdf-table pd2 sheet font11">
        <thead>
            <tr>
                <th rowspan="2" style="width: 10%" class="text-center">Code</th>
                <th rowspan="2" class="text-center">Group</th>
                <th colspan="2" class="text-center">Opening</th>
                <th colspan="2" class="text-center">Add</th>
                <th colspan="2" class="text-center">Less</th>
                <th colspan="2" class="text-center">Closing</th>
            </tr>
            <tr>
                @for ($i = 0; $i < 4; $i++)
                    <th style="width: 7%" class="text-center">Pcs</th>
                    <th style="width: 10%" class="text-center">Wt</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="text-center">{{ $row->code }}</td>
                    <td class="text-left">{{ $row->name }}</td>
                    <td class="text-right">{{ $pcs($row->opening_pcs) }}</td>
                    <td class="text-right">{{ $wt($row->opening_wt) }}</td>
                    <td class="text-right">{{ $pcs($row->add_pcs) }}</td>
                    <td class="text-right">{{ $wt($row->add_wt) }}</td>
                    <td class="text-right">{{ $pcs($row->less_pcs) }}</td>
                    <td class="text-right">{{ $wt($row->less_wt) }}</td>
                    <td class="text-right text-bold">{{ $pcs($row->closing_pcs) }}</td>
                    <td class="text-right text-bold">{{ $wt($row->closing_wt) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="background-light">
                <td colspan="2" class="text-right text-bold">Total</td>
                <td class="text-right text-bold">{{ $pcs($totals->opening_pcs) }}</td>
                <td class="text-right text-bold">{{ $wt($totals->opening_wt) }}</td>
                <td class="text-right text-bold">{{ $pcs($totals->add_pcs) }}</td>
                <td class="text-right text-bold">{{ $wt($totals->add_wt) }}</td>
                <td class="text-right text-bold">{{ $pcs($totals->less_pcs) }}</td>
                <td class="text-right text-bold">{{ $wt($totals->less_wt) }}</td>
                <td class="text-right text-bold">{{ $pcs($totals->closing_pcs) }}</td>
                <td class="text-right text-bold">{{ $wt($totals->closing_wt) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection

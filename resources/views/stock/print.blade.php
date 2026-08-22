{{--
    The stock sheet: both breakdowns on one page. Tables only, as dompdf's support
    for modern layout is poor.
--}}
@extends('layouts.pdf')

@php
    $wt = fn ($v) => number_format((float) $v, 3);
    $pcs = fn ($v) => number_format((int) $v);
@endphp

@section('styles')
    <style>
        body { font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 1mm 0; }
        h2 { font-size: 14px; margin: 5mm 0 2mm 0; }
        .meta { text-align: right; font-weight: bold; font-size: 13px; margin-bottom: 3mm; }
        table.sheet { width: 100%; table-layout: fixed; }
    </style>
@endsection

@section('content')
    <h1>Stock</h1>
    <div class="meta">
        {{ $metalTypeName ?? 'All metal types' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y') }}
    </div>

    @foreach ([
        ['title' => 'By Item Group', 'label' => 'Group', 'rows' => $itemGroups, 'totals' => $itemGroupTotals],
        ['title' => 'By Stock Group', 'label' => 'Stock Group', 'rows' => $stockGroups, 'totals' => $stockGroupTotals],
    ] as $table)
        <h2>{{ $table['title'] }}</h2>

        <table class="pdf-table pd2 sheet font12">
            <thead>
                <tr>
                    <th style="width: 14%" class="text-center">Code</th>
                    <th class="text-center">{{ $table['label'] }}</th>
                    <th style="width: 12%" class="text-center">Pcs</th>
                    <th style="width: 12%" class="text-center">Held</th>
                    <th style="width: 18%" class="text-center">Gross Wt</th>
                    <th style="width: 18%" class="text-center">Net Wt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($table['rows'] as $row)
                    <tr>
                        <td class="text-center">{{ $row->code }}</td>
                        <td class="text-left">{{ $row->name }}</td>
                        <td class="text-right">{{ $pcs($row->pcs) }}</td>
                        <td class="text-right">{{ $row->held > 0 ? $pcs($row->held) : '' }}</td>
                        <td class="text-right">{{ $wt($row->gross) }}</td>
                        <td class="text-right">{{ $wt($row->net) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="background-light">
                    <td colspan="2" class="text-right text-bold">Total</td>
                    <td class="text-right text-bold">{{ $pcs($table['totals']->pcs) }}</td>
                    <td class="text-right text-bold">{{ $pcs($table['totals']->held) }}</td>
                    <td class="text-right text-bold">{{ $wt($table['totals']->gross) }}</td>
                    <td class="text-right text-bold">{{ $wt($table['totals']->net) }}</td>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection

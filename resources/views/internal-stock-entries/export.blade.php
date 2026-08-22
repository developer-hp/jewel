{{--
    The internal stock sheet. Built on layouts/pdf with the utilities from
    public/css/pdf.css — tables only, as dompdf's support for modern layout is poor.
--}}
@extends('layouts.pdf')

@php($weight = fn ($v) => (float) $v > 0 ? rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') : '')

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 2mm 0;
        }

        .date {
            text-align: right;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3mm;
        }

        table.sheet {
            width: 100%;
            table-layout: fixed;
        }

        .empty {
            margin-top: 6mm;
            font-style: italic;
        }
    </style>
@endsection

@section('content')
    <h1>Internal Stock</h1>
    <div class="date">Date: {{ now()->format('d/m/Y') }}</div>

    @if ($entries->isEmpty())
        <div class="empty">No stock entries to show.</div>
    @else
        <table class="pdf-table pd3 sheet font12">
            <thead>
                <tr>
                    <th style="width: 18%" class="text-center">Type</th>
                    <th style="width: 24%" class="text-center">Internal Stock</th>
                    <th style="width: 15%" class="text-center">IN</th>
                    <th style="width: 15%" class="text-center">Out</th>
                    <th class="text-center">Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td class="text-center">{{ $entry->type }}</td>
                        <td class="text-center">{{ $entry->internalStock?->name }}</td>
                        <td class="text-right">{{ $entry->isOutgoing() ? '' : $weight($entry->weight) }}</td>
                        <td class="text-right">{{ $entry->isOutgoing() ? $weight($entry->weight) : '' }}</td>
                        <td class="text-left">{{ $entry->note }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="background-light">
                    <td colspan="2" class="text-center text-bold">TOTAL</td>
                    <td class="text-right text-bold">{{ $weight($totalIn) }}</td>
                    <td class="text-right text-bold">{{ $weight($totalOut) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection

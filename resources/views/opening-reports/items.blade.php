{{--
    One of the day opening's reports. The same layout serves sold and added items —
    only the heading, the date column and whether photos are drawn differ.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only, and
    every <img> is display:block, or the inline descender gap spills a row onto a
    second page.
--}}
@extends('layouts.pdf')

@php
    $weight = fn ($v) => number_format((float) $v, 3);
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
            font-size: 13px;
            margin-bottom: 3mm;
        }

        table.lines {
            width: 100%;
            table-layout: fixed;
        }

        .photo img {
            max-width: 18mm;
            max-height: 18mm;
            display: block;
            margin: 0 auto;
        }
    </style>
@endsection

@section('content')
    <h1>{{ $heading }}</h1>

    <div class="meta">
        {{ $since->year > 1971 ? $since->format('d-m-Y H:i') : 'the beginning' }}
        &rarr; {{ $until->format('d-m-Y H:i') }}
    </div>

    @if ($items->isEmpty())
        <p class="text-bold">Nothing in this period.</p>
    @else
        <table class="pdf-table pd2 lines font11">
            <thead>
                <tr>
                    @if ($showPhotos)
                        <th style="width: 22mm" class="text-center">Photo</th>
                    @endif
                    <th style="width: 18%" class="text-center">Code</th>
                    <th class="text-center">Item</th>
                    <th style="width: 16%" class="text-center">Metal</th>
                    <th style="width: 12%" class="text-center">Gross</th>
                    <th style="width: 12%" class="text-center">Net</th>
                    <th style="width: 16%" class="text-center">
                        {{ $dateColumn === 'sold' ? 'Sold' : 'Added' }}
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    @php($stamp = $dateColumn === 'sold' ? $item->sold_at : $item->created_at)
                    <tr>
                        @if ($showPhotos)
                            <td class="text-center photo">
                                @if ($item->photoDataUri())
                                    <img src="{{ $item->photoDataUri() }}" alt="">
                                @endif
                            </td>
                        @endif
                        <td class="text-center text-bold">{{ $item->code }}</td>
                        <td class="text-left">
                            {{ $item->name }}
                            @if ($item->itemGroup)
                                <div class="font10">{{ $item->itemGroup->name }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            {{ trim(($item->metalType?->name ?? '') . ' ' . ($item->purity?->name ?? '')) }}
                        </td>
                        <td class="text-right">{{ $weight($item->gross_weight) }}</td>
                        <td class="text-right">{{ $weight($item->net_weight) }}</td>
                        <td class="text-center">{{ $stamp?->format('d-m-Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="{{ $showPhotos ? 4 : 3 }}" class="text-center text-bold">
                        TOTAL &mdash; {{ $items->count() }} pcs
                    </td>
                    <td class="text-right text-bold">{{ $weight($items->sum('gross_weight')) }}</td>
                    <td class="text-right text-bold">{{ $weight($items->sum('net_weight')) }}</td>
                    <td>&nbsp;</td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection

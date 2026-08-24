{{--
    The OG rough estimate: a customer copy above a FOR OFFICE USE copy, then the
    footer box the office signs off.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only —
    dompdf's support for flexbox and grid is poor.
--}}
@extends('layouts.pdf')

@php
    $rowsPerCopy = 10;
    $wt = fn ($v) => number_format((float) $v, 3);
    $money = fn ($v) => number_format((float) $v, 0);
@endphp

@section('styles')
    <style>
        @include('og-estimates.partials._styles')
    </style>
@endsection

@section('content')
    @foreach ($estimates as $estimate)
        @include('og-estimates.partials._document', [
            'estimate' => $estimate,
            'rowsPerCopy' => $rowsPerCopy,
            'last' => $loop->last,
        ])
    @endforeach
@endsection

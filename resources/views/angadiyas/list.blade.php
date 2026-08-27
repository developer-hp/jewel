{{--
    The despatch list — one line per slip, for whoever hands the parcels over.

    Built on layouts/pdf with the utilities from public/css/pdf.css, which is where the
    ruled table and the shaded header come from. Tables only.
--}}
@extends('layouts.pdf')

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        h1 {
            font-size: 22px;
            text-align: center;
            margin: 0 0 2mm 0;
        }

        .meta {
            text-align: right;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3mm;
        }

        table.slips {
            width: 100%;
            table-layout: fixed;
        }

        /* A long remark has to wrap rather than widen the column and push the page
           sideways. */
        table.slips td {
            vertical-align: top;
        }
    </style>
@endsection

@section('content')
    <h1>Angadiya</h1>

    <div class="meta">Date: {{ now()->format('d/m/Y') }}</div>

    <table class="pdf-table pd2 slips font12">
        <thead>
            <tr>
                <th style="width: 6%" class="text-center">No</th>
                <th style="width: 15%" class="text-center">City</th>
                <th style="width: 18%" class="text-center">Name</th>
                <th style="width: 15%" class="text-center">Mobile</th>
                <th style="width: 13%" class="text-center">Insurance</th>
                <th class="text-center">Remark</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($slips as $i => $slip)
                <tr>
                    <td class="text-left">{{ $i + 1 }}</td>
                    <td class="text-left">{{ $slip->city }}</td>
                    <td class="text-left">{{ $slip->name }}</td>
                    <td class="text-left">{{ $slip->mobile }}</td>
                    <td class="text-right">{{ number_format((float) $slip->insurance_amount, 0) }}</td>
                    <td class="text-left">{{ $slip->remark }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-center text-bold">TOTAL &mdash; {{ $slips->count() }} slips</td>
                <td class="text-right text-bold">
                    {{ number_format((float) $slips->sum('insurance_amount'), 0) }}
                </td>
                <td>&nbsp;</td>
            </tr>
        </tfoot>
    </table>
@endsection

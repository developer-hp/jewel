{{--
    The sticker that goes on the bag the goods travel in.

    The Ready column is left blank on purpose — the workshop ticks it by hand as
    each piece is finished. What the system considers ready is read off stock, on
    the repair listing.
--}}
@extends('layouts.pdf')

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        .sticker {
            page-break-inside: avoid;
            margin-bottom: 10mm;
            width: 95mm;
        }

        .ref {
            font-size: 34px;
            font-weight: bold;
            margin: 0 0 1mm 0;
        }

        .who {
            font-size: 15px;
            font-weight: bold;
            line-height: 1.35;
        }

        table.items {
            width: 95mm;
            table-layout: fixed;
            margin-top: 2mm;
        }
    </style>
@endsection

@section('content')
    @foreach ($forms as $form)
        <div class="sticker">
            <div class="ref">{{ $form->reference() }}</div>

            <div class="who">
                M/S. : {{ $form->customer_name }}<br>
                M No. : {{ $form->contact_no }}<br>
                Delivery Date : {{ $form->delivery_date->format('d-m-Y') }}
            </div>

            <table class="pdf-table pd2 items font12">
                <thead>
                    <tr>
                        <th style="width: 30mm" class="text-center">Items</th>
                        <th style="width: 18mm" class="text-center">Ready</th>
                        <th class="text-center">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($form->lines as $i => $line)
                        <tr>
                            <td class="text-left">Item {{ $i + 1 }}</td>
                            {{-- Ticked by hand in the workshop. --}}
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-left text-bold">Remarks : {{ $form->remarks }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach
@endsection

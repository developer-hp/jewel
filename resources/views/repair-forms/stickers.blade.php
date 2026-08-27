{{--
    The sticker that goes on the bag the goods travel in.

    Two layouts, one markup. A single sticker prints on 105 x 160mm cut stock; two or
    more go four-up on plain A4 to be guillotined apart. The controller picks the paper
    and hands down $columns / $perSheet / $cellHeightMm, so the sticker itself is
    written once.

    The grid is a table, not floats: mPDF's float support is unreliable and a floated
    row will not hold a page break where it is told to.

    The Ready column is left blank on purpose — the workshop ticks it by hand as
    each piece is finished. What the system considers ready is read off stock, on
    the repair listing.
--}}
@extends('layouts.pdf')

@php
    $columns = $columns ?? 1;
    $perSheet = $perSheet ?? 1;
    $cellHeightMm = $cellHeightMm ?? 152.0;
@endphp

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        table.sheet {
            width: 100%;
            border-collapse: collapse;
        }

        table.sheet tr {
            page-break-inside: avoid;
        }

        /* One sticker per cell. The cell carries no border of its own — pdf.css
           borders every td, so it has to be taken off explicitly, and the dashed
           rule below is the guillotine guide instead. */
        td.cut {
            width: {{ round(100 / $columns, 4) }}%;
            height: {{ $cellHeightMm }}mm;
            vertical-align: top;
            border: none;
            padding: 10px;
        }

        @if ($columns > 1)
            /* Only worth a cut line when there is something to cut apart. */
            td.cut {
                border: 1px dashed #999;
            }
        @endif

        .sticker {
            padding: 4mm;
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
    </style>
@endsection

@section('content')
    @foreach ($forms->chunk($perSheet) as $sheetIndex => $sheet)
        <table class="sheet" @if (! $loop->last) style="page-break-after: always;" @endif>
            @foreach ($sheet->chunk($columns) as $row)
                <tr>
                    @foreach ($row as $form)
                        <td class="cut">
                            <div class="sticker">
                                <div class="ref">{{ $form->reference() }}</div>

                                <div class="who">
                                    M/S. : {{ $form->customer_name }}<br>
                                    M No. : {{ $form->contact_no }}<br>
                                    Delivery Date : {{ $form->delivery_date->format('d-m-Y') }}
                                </div>

                                <table class="pdf-table pd2 items font12 mt10">
                                    <thead>
                                        <tr>
                                            <th style="width: 20mm" class="text-center">Items</th>
                                            <th style="width: 10mm" class="text-center">Ready</th>
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
                                            <td colspan="3" class="text-left text-bold">
                                                Remarks : {{ $form->remarks }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </td>
                    @endforeach

                    {{-- Pad the last row so the remaining stickers keep their width
                         and land where the guillotine expects them. --}}
                    @for ($i = $row->count(); $i < $columns; $i++)
                        <td class="cut"></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endforeach
@endsection

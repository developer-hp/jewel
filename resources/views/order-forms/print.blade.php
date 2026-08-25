{{--
    The order form, printed twice: the copy the customer takes away and brings back
    at delivery, and the office copy that stays behind.

    The office copy carries what the counter needs and the customer does not — the
    address, the sales person, and the code of any piece already made against a line
    — the same split the estimate print uses.

    Built on layouts/pdf with the utilities from public/css/pdf.css. Tables only, as
    support for flexbox and grid is poor in a PDF renderer. The lines table is padded
    out to ten rows as on the paper original, so there is room to write more by hand.
--}}
@extends('layouts.pdf')

@php
    $rowsPerForm = 10;
    // The piece a line refers to: the one held against it, else the one it was
    // picked from in stock. Only the office copy prints it.
    $pieceOf = fn ($line) => $line->item ?? $line->sourceItem;
    $weight = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '';
    $money = fn ($v) => (float) $v > 0 ? number_format((float) $v, 0) : '0';
@endphp

@section('styles')
    <style>
        body {
            font-size: 12px;
        }

        .form-page {
            page-break-after: always;
        }

        .form-page.last {
            page-break-after: auto;
        }

        .title {
            font-size: 26px;
            font-weight: bold;
            line-height: 1.1;
        }

        .refbox {
            padding: 2mm;
        }

        table.lines {
            width: 100%;
            table-layout: fixed;
            margin-top: 3mm;
        }

        table.lines td {
            height: 6mm;
        }

        .bring {
            border: 1px solid;
            border-top: none;
            padding: 1.2mm;
            text-align: center;
            font-weight: bold;
        }

        .terms .head {
            font-weight: bold;
            font-size: 13px;
        }

        .terms .line {
            font-weight: bold;
            font-size: 9px;
        }

        .signbox {
            border: 1px solid;
            height: 22mm;
            text-align: center;
            vertical-align: bottom;
        }

        /* The office copy's piece detail, under the description.

           Deliberately <div>s and not a nested <table>. mPDF keeps a map of where
           each nested table sits inside its parent cell, and inside this particular
           table — fixed layout, seven columns, a tfoot with colspans — that map
           comes back null and the render dies with "Trying to access array offset
           on null" in Mpdf\Tag\Table. Laravel turns that warning into a 500, so
           the whole print fails. Plain blocks cannot hit it. */
        .itemdetail {
            margin-top: 0.8mm;
            font-size: 9px;
            font-weight: bold;
            color: #333;
        }

        .linephoto img {
            max-width: 15mm;
            max-height: 15mm;
            display: block;
        }
    </style>
@endsection

@section('content')
    @foreach ($forms as $form)
        @foreach (['customer', 'office'] as $copy)
        {{-- Each copy is a full page: ten line rows, the terms and the signatures
             already fill one, so halving them would make both unusable. --}}
        <div class="form-page @if ($loop->parent->last && $copy === 'office') last @endif">

            <table class="pdf-table no-border" style="width: 100%;">
                <tr>
                    <td class="no-border title" style="width: 45%; vertical-align: top;">
                        ORDER FORM
                        @if ($copy === 'office')
                            <div class="font13">OFFICE COPY</div>
                        @endif
                    </td>
                    <td class="no-border text-right" style="vertical-align: top;">
                        <span class="text-bold font15">For Query : {{ $firm['query_phone'] }}</span>
                        @if ($firm['website'])
                            <br><span class="text-bold font11">{{ $firm['website'] }}</span>
                        @endif
                        @if ($copy === 'office' && $form->sales_person_name)
                            <br><span class="text-bold font11">Sales : {{ $form->sales_person_name }}</span>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="pdf-table no-border" style="width: 100%; margin-top: 2mm;">
                <tr>
                    <td class="no-border" style="width: 58%; vertical-align: bottom;">
                        <div class="text-bold font16">M/S. : {{ $form->customer_name }}</div>
                        <div class="text-bold font16">Contact No. : {{ $form->contact_no }}</div>
                        @if ($copy === 'office' && $form->address)
                            <div class="text-bold font11">{{ $form->address }}</div>
                        @endif
                    </td>
                    <td class="no-border" style="width: 42%;">
                        <div class="refbox text-bold font13">
                            Order No. : {{ $form->reference() }}<br>
                            Date : {{ $form->form_date->format('d-m-Y') }}<br>
                            Delivery Date : {{ $form->delivery_date->format('d-m-Y') }}
                        </div>
                    </td>
                </tr>
            </table>

            <table class="pdf-table pd2 lines font11">
                <thead>
                    <tr>
                        <th style="width: 9%" class="text-center">No.</th>
                        <th class="text-center">Particulars</th>
                        <th style="width: 11%" class="text-center">Pcs Size</th>
                        <th style="width: 14%" class="text-center">Approx Net Weight</th>
                        <th style="width: 14%" class="text-center">Labour per gm</th>
                        <th style="width: 13%" class="text-center">Approx O.C.</th>
                        <th style="width: 13%" class="text-center">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($form->lines as $i => $line)
                        @php($piece = $copy === 'office' ? $pieceOf($line) : null)
                        @php($photo = $copy === 'office' ? ($piece?->photoDataUri() ?: $line->photoDataUri()) : null)
                        <tr>
                            <td class="text-center">
                                @if ($photo)
                                    <div class="linephoto"><img src="{{ $photo }}" alt=""></div>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </td>
                            <td class="text-left">
                                @if ($piece)
                                    <span class="text-bold">{{ $piece->code }}</span>
                                @endif
                                {{ $line->description }}

                                @if ($piece)
                                    {{-- Everything the counter needs to find and check
                                         the piece without opening the system. --}}
                                    <div class="itemdetail">
                                        {{ collect([
                                            $piece->metalType?->name,
                                            $piece->purity?->name,
                                            'GW ' . $weight($piece->gross_weight),
                                            'NW ' . $weight($piece->net_weight),
                                            $piece->makingCharge
                                                ? 'LB ' . ($piece->makingCharge->code ?: $piece->makingCharge->name)
                                                : null,
                                        ])->filter()->implode(' · ') }}
                                    </div>

                                    @foreach ($piece->itemStones as $stone)
                                        <div class="itemdetail">
                                            {{ collect([
                                                $stone->stoneMaster?->code ?: $stone->stoneMaster?->name,
                                                (float) $stone->weight_carat > 0 ? $weight($stone->weight_carat) . ' ct' : null,
                                                $stone->pieces ? $stone->pieces . ' pc' : null,
                                                '@ ' . $money($stone->rate),
                                                '= ' . $money($stone->amount),
                                            ])->filter()->implode('  ') }}
                                        </div>
                                    @endforeach

                                    @foreach ($piece->extraChargeLines() as $charge)
                                        <div class="itemdetail">
                                            {{ $charge['label'] }}  = {{ $money($charge['amount']) }}
                                        </div>
                                    @endforeach
                                @endif
                            </td>
                            <td class="text-center">{{ $line->size_pcs }}</td>
                            <td class="text-right">{{ $weight($line->net_weight) }}</td>
                            <td class="text-right">{{ $line->labourLabel() }}</td>
                            <td class="text-right">{{ $money($line->oc_amount) }}</td>
                            <td class="text-right">{{ $line->rateLabel() }}</td>
                        </tr>
                    @endforeach

                    {{-- Padded to ten, as on the paper original. --}}
                    @for ($i = $form->lines->count(); $i < $rowsPerForm; $i++)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-center text-bold">Total Netweight</td>
                        <td class="text-right text-bold">{{ $weight($form->totalNetWeight()) }}</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="text-left text-bold">Remarks : {{ $form->remarks }}</td>
                    </tr>
                </tfoot>
            </table>

            @if ($copy === 'customer')
                <div class="bring">PLEASE BRING THIS ORDER FORM ALONG WITH YOU DURING DELIVERY</div>
            @endif

            <table class="pdf-table no-border" style="width: 100%; margin-top: 3mm;">
                <tr>
                    <td class="no-border" style="width: 52%; vertical-align: top;">
                        @if ($terms !== [])
                            <div class="terms">
                                <div class="head">Terms and Conditions:</div>
                                @foreach ($terms as $term)
                                    <div class="line">{{ $term }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="no-border signbox" style="width: 24%;">Approved By<br>Customer</td>
                    <td class="no-border" style="width: 2%;">&nbsp;</td>
                    <td class="no-border signbox" style="width: 22%;">&nbsp;</td>
                </tr>
            </table>

        </div>
        @endforeach
    @endforeach
@endsection

{{--
    One OG estimate: the customer copy, the office copy and the sign-off box.

    Shared between the OG estimate's own print and the page an item estimate attaches
    behind it. Requires the rules in og-estimates/partials/_styles.

    $estimate    the OgEstimate to render
    $rowsPerCopy how many line slots to pad out to
    $last        true to suppress the trailing page break
--}}
@php
    // Defined here rather than inherited, so the partial stands on its own when the
    // item estimate attaches this document behind its own pages.
    $totals = $estimate->totals();
    $rowsPerCopy = $rowsPerCopy ?? 10;
    $wt = fn ($v) => number_format((float) $v, 3);
    $money = fn ($v) => number_format((float) $v, 0);
@endphp

<div class="form-page @if ($last ?? false) last @endif">

            @foreach (['customer', 'office'] as $copy)
                <div class="copy-title">{{ $copy === 'office' ? 'FOR OFFICE USE' : 'OG ROUGH ESTIMATE' }}</div>

                <table class="pdf-table no-border who mt5 font13">
                    <tr>
                        <td class="no-border" style="width: 52%;">
                            <span class="text-bold">Name</span>&nbsp;&nbsp;&nbsp;{{ $estimate->customer_name }}
                            @if ($copy === 'office' && $estimate->address)
                                <br><span class="text-bold">Address</span>&nbsp;&nbsp;{{ $estimate->address }}
                            @endif
                        </td>
                        <td class="no-border" style="width: 30%;">
                            <span class="text-bold">Mobile</span>&nbsp;&nbsp;&nbsp;{{ $estimate->contact_no }}
                        </td>
                        <td class="no-border text-right" style="width: 18%;">
                            @if ($copy === 'office')
                                <span class="text-bold font12">{{ $estimate->sales_person_name }}</span><br>
                                <span class="text-bold">Ref No:{{ $estimate->reference() }}</span><br>
                            @endif
                            <span class="text-bold font14">{{ $estimate->estimate_date->format('d-m-Y') }}</span>
                        </td>
                    </tr>
                </table>

                <table class="layout" style="width: 100%;">
                    <tr>
                        <td class="og-cell" style="width: 86%;">
                            <table class="no-border font13" style="text-align: center;border: 0px;" width="100%">
                                <thead>
                                    <tr>
                                        <th class="text-center">ITEM</th>
                                        <th style="width: 15%" class="text-right">GROSS</th>
                                        <th style="width: 15%" class="text-right">NET WT</th>
                                        <th style="width: 12%" class="text-right">%</th>
                                        <th style="width: 15%" class="text-right">RATE</th>
                                        <th style="width: 16%" class="text-right">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($estimate->lines as $line)
                                        <tr>
                                            <td class="text-center">{{ $line->description }}</td>
                                            <td class="text-right">{{ $wt($line->gross_weight) }}</td>
                                            <td class="text-right">{{ $wt($line->net_weight) }}</td>
                                            <td class="text-right">{{ rtrim(rtrim($wt($line->touch_percent), '0'), '.') }}</td>
                                            <td class="text-right">{{ $money($line->rate) }}</td>
                                            <td class="text-right">{{ $money($line->value()) }}</td>
                                        </tr>
                                    @endforeach

                                    {{-- Padded out, so there is room to write more in by hand. --}}
                                    @for ($i = $estimate->lines->count(); $i < $rowsPerCopy; $i++)
                                        <tr>
                                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="text-center text-bold">TOTAL</th>
                                        <th class="text-right text-bold">{{ $wt($totals->gross) }}</th>
                                        <th class="text-right text-bold">{{ $wt($totals->net) }}</th>
                                        {{-- The fine weight, not an average percentage. --}}
                                        <th class="text-right text-bold">{{ $wt($totals->fine) }}</th>
                                        <th>&nbsp;</td>
                                        <th class="text-right text-bold">{{ $money($totals->value) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </td>
                        <td class="og-cell grand" style="width: 14%; vertical-align: top;">
                            <div class="grandbox text-center">{{ $money($totals->value) }}</div>
                        </td>
                    </tr>
                </table>

                {{-- An empty div has no height in mPDF, so the gap between the two
                     copies needs something in it to hold the line box open. --}}
                @if ($copy === 'customer')
                    <div class="copy-gap">&nbsp;</div>
                @endif
            @endforeach

            <table class="pdf-table pd3 signoff font13">
                <tr>
                    <th class="text-center">Date</th>
                    <th class="text-center">Gross</th>
                    <th class="text-center">Amount</th>
                    <th class="text-center">Received</th>
                    <th class="text-center">Approved</th>
                    <th class="text-center">Ref No:{{ $estimate->reference() }}</th>
                </tr>
                <tr>
                    <td class="text-center">{{ $estimate->estimate_date->format('d-m-Y') }}</td>
                    <td class="text-center">{{ $wt($totals->gross) }}</td>
                    <td class="text-center">{{ $money($totals->value) }}</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td class="text-center">{{ $estimate->sales_person_name }}</td>
                </tr>
            </table>

</div>

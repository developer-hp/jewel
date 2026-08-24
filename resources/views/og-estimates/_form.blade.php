@csrf

<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">OG Estimate</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Ref No</label>
                {{-- Issued by the system under a lock, so it is never typed. --}}
                <input type="text" class="form-control bg-light" value="{{ $nextRef }}" readonly>
                @unless ($estimate->exists)
                    <small class="text-muted">Assigned on save.</small>
                @endunless
            </div>

            <div class="col-md-3">
                <label for="estimate_date" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="estimate_date" name="estimate_date"
                    class="form-control @error('estimate_date') is-invalid @enderror"
                    value="{{ old('estimate_date', optional($estimate->estimate_date)->toDateString() ?? today()->toDateString()) }}"
                    required>
                @error('estimate_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="sales_person_id" class="form-label">Sales Person <span class="text-danger">*</span></label>
                <select id="sales_person_id" name="sales_person_id"
                    class="form-select @error('sales_person_id') is-invalid @enderror" required>
                    <option value="">Select Sales Person</option>
                    @foreach ($salesPersons as $person)
                        <option value="{{ $person->id }}"
                            @selected(old('sales_person_id', $estimate->sales_person_id) == $person->id)>
                            {{ $person->name }}@if ($person->city) ({{ $person->city }})@endif
                        </option>
                    @endforeach
                </select>
                @error('sales_person_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                @include('partials._order-reference-select', [
                    'record' => $estimate,
                    'orderForms' => $orderForms,
                ])
            </div>

            <div class="col-md-3">
                <label for="contact_no" class="form-label">Mobile</label>
                <input type="text" id="contact_no" name="contact_no"
                    class="form-control @error('contact_no') is-invalid @enderror"
                    value="{{ old('contact_no', $estimate->contact_no) }}" maxlength="30" autocomplete="off">
                @error('contact_no')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                <input type="text" id="customer_name" name="customer_name"
                    class="form-control @error('customer_name') is-invalid @enderror"
                    value="{{ old('customer_name', $estimate->customer_name) }}" maxlength="150"
                    autocomplete="off" required>
                {{-- Filled in when the number matches someone already known. --}}
                <small class="text-success d-none" id="customer-hint"></small>
                @error('customer_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-5">
                <label for="address" class="form-label">Address</label>
                <textarea id="address" name="address" rows="1" class="form-control"
                    maxlength="1000">{{ old('address', $estimate->address) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="text-muted fs-13 mb-0">
                Rate is per ten grams. The total is net &times; % &divide; 100 &times; rate &divide; 10;
                a row left blank is ignored on save.
            </p>
            <button type="button" class="btn btn-dark" id="est-add-row">
                <i class="ri-add-line"></i> Add
            </button>
        </div>

        @error('lines')
            <div class="alert alert-danger py-2 fs-13">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table table-sm table-centered mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 30%">Description</th>
                        <th style="width: 13%">Gross</th>
                        <th style="width: 13%">Net Wt</th>
                        <th style="width: 11%">%</th>
                        <th style="width: 14%">Rate</th>
                        <th style="width: 15%">Total</th>
                        <th style="width: 4%"></th>
                    </tr>
                </thead>
                <tbody id="est-body">
                    @foreach ($lines as $i => $line)
                        @include('og-estimates.partials._line-row', ['index' => $i, 'line' => $line])
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td class="text-end">TOTAL</td>
                        <td id="est-total-gross">0.000</td>
                        <td id="est-total-net">0.000</td>
                        {{-- The fine weight sits under %, as on the printed form: it is
                             the total pure gold, not an average percentage. --}}
                        <td id="est-total-fine" title="Total fine weight">0.000</td>
                        <td></td>
                        <td id="est-total-value">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<template id="est-row-template">
    @include('og-estimates.partials._line-row', ['index' => '__INDEX__', 'line' => null])
</template>

<div class="mb-4 d-flex gap-2 justify-content-center">
    <a href="{{ route('og-estimates.index') }}" class="btn btn-warning">Cancel</a>

    <button type="submit" class="btn btn-dark">
        <i class="ri-save-line"></i> {{ $estimate->exists ? 'Update' : 'Save' }}
    </button>

    @can('og_estimate.print')
        @unless ($estimate->exists)
            <button type="submit" name="print_after_save" value="1" class="btn btn-secondary">
                <i class="ri-printer-line"></i> Save &amp; Print
            </button>
        @endunless
    @endcan
</div>

@include('partials._customer-lookup', ['contactField' => 'contact_no'])

@push('js')
    <script>
        $(function () {
            let nextIndex = {{ $lines->count() }};

            // Mirrors EstimateLineMath so the clerk sees the figures move; the server
            // recomputes them from the same rule on save.
            function fine(net, touch) {
                return Math.round(net * touch / 100 * 1000) / 1000;
            }

            function refresh() {
                let gross = 0, net = 0, totalFine = 0, value = 0;

                $('#est-body tr.estimate-row').each(function () {
                    const $row = $(this);
                    const g = parseFloat($row.find('.est-gross').val()) || 0;
                    const n = parseFloat($row.find('.est-net').val()) || 0;
                    const t = parseFloat($row.find('.est-touch').val()) || 0;
                    const r = parseFloat($row.find('.est-rate').val()) || 0;

                    const f = fine(n, t);
                    const v = Math.round(f * r / 10 * 100) / 100;

                    $row.find('.est-total').val(v ? v.toFixed(2) : '');

                    gross += g;
                    net += n;
                    totalFine += f;
                    value += v;
                });

                $('#est-total-gross').text(gross.toFixed(3));
                $('#est-total-net').text(net.toFixed(3));
                $('#est-total-fine').text(totalFine.toFixed(3));
                $('#est-total-value').text(value.toLocaleString('en-IN', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                }));
            }

            $('#est-add-row').on('click', function () {
                $('#est-body').append($('#est-row-template').html().replace(/__INDEX__/g, nextIndex++));
                refresh();
            });

            $(document).on('click', '.est-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            $(document).on('input', '#est-body input', refresh);

            // A new estimate opens with one blank row ready to fill.
            if (nextIndex === 0) {
                $('#est-add-row').trigger('click');
            }

            refresh();
        });
    </script>
@endpush

@csrf

<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Voucher</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Ref No</label>
                        {{-- Issued by the system under a lock, so it is never typed. --}}
                        <input type="text" class="form-control bg-light" value="{{ $nextRef }}" readonly>
                        @unless ($voucher->exists)
                            <small class="text-muted">Assigned on save.</small>
                        @endunless
                    </div>

                    <div class="col-md-4">
                        <label for="voucher_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" id="voucher_date" name="voucher_date"
                            class="form-control @error('voucher_date') is-invalid @enderror"
                            value="{{ old('voucher_date', optional($voucher->voucher_date)->toDateString() ?? today()->toDateString()) }}"
                            required>
                        @error('voucher_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="sales_person_id" class="form-label">
                            Sales Person <span class="text-danger">*</span>
                        </label>
                        <select id="sales_person_id" name="sales_person_id"
                            class="form-select @error('sales_person_id') is-invalid @enderror" required>
                            <option value="">Select Sales Person</option>
                            @foreach ($salesPersons as $person)
                                <option value="{{ $person->id }}"
                                    @selected(old('sales_person_id', $voucher->sales_person_id) == $person->id)>
                                    {{ $person->name }}@if ($person->city) ({{ $person->city }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('sales_person_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="payment_mode" class="form-label">
                            Cash / Cheque <span class="text-danger">*</span>
                        </label>
                        <select id="payment_mode" name="payment_mode"
                            class="form-select @error('payment_mode') is-invalid @enderror" required>
                            @foreach (\App\Models\Voucher::MODES as $value => $label)
                                <option value="{{ $value }}"
                                    @selected(old('payment_mode', $voucher->payment_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_mode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-8">
                        @include('partials._order-reference-select', [
                            'record' => $voucher,
                            'orderForms' => $orderForms,
                        ])
                    </div>

                    <div class="col-md-8">
                        <label for="description" class="form-label">
                            Description <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="description" name="description"
                            class="form-control @error('description') is-invalid @enderror"
                            value="{{ old('description', $voucher->description) }}" maxlength="255" required>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                            class="form-control @error('amount') is-invalid @enderror"
                            value="{{ old('amount', $voucher->exists ? (float) $voucher->amount : '') }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-center mt-3">
                    <a href="{{ route('vouchers.index') }}" class="btn btn-warning">Cancel</a>

                    <button type="submit" class="btn btn-dark">
                        <i class="ri-save-line"></i> {{ $voucher->exists ? 'Update' : 'Save' }}
                    </button>

                    @can('voucher.print')
                        @unless ($voucher->exists)
                            <button type="submit" name="print_after_save" value="1" class="btn btn-secondary">
                                <i class="ri-printer-line"></i> Save &amp; Print
                            </button>
                        @endunless
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
    <script>
        $(function () {
            // Picking an order fills the description with its reference and shows who
            // it belongs to, as on the paper voucher.
            $('#order_reference').on('change', function () {
                const $option = $(this).find('option:selected');
                const reference = $option.data('reference');
                const $hint = $('#order-customer-hint');

                if (!reference) {
                    $hint.addClass('d-none').text('');

                    return;
                }

                $hint.removeClass('d-none')
                    .text(($option.data('customer') || '') + '  ' + ($option.data('contact') || ''));

                // Only when the clerk has not written their own description.
                if (! $('#description').val().trim()) {
                    $('#description').val(reference);
                }
            }).trigger('change');
        });
    </script>
@endpush

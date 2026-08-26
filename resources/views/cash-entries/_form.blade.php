@csrf

@php
    $math = app(App\Services\CashMath::class);
    $goldWeight = (float) $entry->gold_weight;
    $goldAmount = (float) $entry->gold_amount;
    $finalAmount = (float) $entry->final_amount;
@endphp

<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Cash Entry</h5>
            </div>
            <div class="card-body">
                {{--
                    Only document_reference and og_estimate_id carry a name. Final
                    Amount, Discount, Gold and Gold Amount are readonly displays with
                    no name at all, and that is deliberate: those figures are read
                    from the documents server-side and are not fillable on the model.
                    Give any of them a name and a crafted post could book five lakh
                    against a five hundred rupee estimate, because the over-payment
                    guard compares against that very figure.
                --}}
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="cash_drawer_id" class="form-label">Drawer <span class="text-danger">*</span></label>
                        <select id="cash_drawer_id" name="cash_drawer_id"
                            class="form-select @error('cash_drawer_id') is-invalid @enderror" required>
                            <option value="">Select Drawer</option>
                            @foreach ($drawers as $drawer)
                                <option value="{{ $drawer->id }}" @selected(old('cash_drawer_id', $entry->cash_drawer_id) == $drawer->id)>
                                    {{ $drawer->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('cash_drawer_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="entry_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" id="entry_date" name="entry_date"
                            class="form-control @error('entry_date') is-invalid @enderror"
                            value="{{ old('entry_date', optional($entry->entry_date)->toDateString() ?? today()->toDateString()) }}"
                            required>
                        @error('entry_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Ref No</label>
                        {{-- Issued by the system under a lock, so it is never typed. --}}
                        <input type="text" class="form-control bg-light" value="{{ $nextRef }}" readonly>
                        @unless ($entry->exists)
                            <small class="text-muted">Assigned on save.</small>
                        @endunless
                    </div>

                    <div class="col-md-8">
                        <label for="document_reference" class="form-label">
                            Item Estimate / Voucher Ref <span class="text-danger">*</span>
                        </label>
                        <select id="document_reference" name="document_reference"
                            class="form-select @error('document_reference') is-invalid @enderror" required>
                            {{-- On edit the chosen document is excluded from the lookup by
                                 its own entry, so it is pre-rendered from the snapshot
                                 rather than fetched. --}}
                            @if ($entry->exists && $entry->documentValue())
                                <option value="{{ $entry->documentValue() }}" selected>
                                    {{ $entry->document_reference }}@if ($entry->party_name) — {{ $entry->party_name }}@endif
                                </option>
                            @endif
                        </select>
                        <small class="text-muted">A document can be settled once.</small>
                        @error('document_reference')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Final Amount</label>
                        <input type="text" id="final-amount" class="form-control bg-light fw-bold" readonly
                            data-value="{{ $finalAmount }}" value="{{ number_format($finalAmount, 2) }}">
                    </div>

                    <div class="col-md-4">
                        <label for="cash_event" class="form-label">Cash Event <span class="text-danger">*</span></label>
                        <select id="cash_event" name="cash_event"
                            class="form-select @error('cash_event') is-invalid @enderror" required>
                            @foreach (\App\Models\CashEntry::EVENTS as $value => $label)
                                <option value="{{ $value }}" @selected(old('cash_event', $entry->cash_event) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">IN adds to the drawer, OUT takes from it.</small>
                        @error('cash_event')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="cash_amount" class="form-label">Cash</label>
                        <input type="number" step="0.01" min="0" id="cash_amount" name="cash_amount"
                            class="form-control @error('cash_amount') is-invalid @enderror"
                            value="{{ old('cash_amount', (float) $entry->cash_amount ?: '') }}">
                        @error('cash_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="cheque_amount" class="form-label">Cheque Amount</label>
                        <input type="number" step="0.01" min="0" id="cheque_amount" name="cheque_amount"
                            class="form-control @error('cheque_amount') is-invalid @enderror"
                            value="{{ old('cheque_amount', (float) $entry->cheque_amount ?: '') }}">
                        @error('cheque_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Only worth showing once there is a cheque to describe. --}}
                <div class="row g-3 mt-0" id="cheque-block">
                    <div class="col-md-3">
                        <label for="cheque_number" class="form-label">Cheque Number</label>
                        <input type="text" id="cheque_number" name="cheque_number"
                            class="form-control @error('cheque_number') is-invalid @enderror"
                            value="{{ old('cheque_number', $entry->cheque_number) }}" maxlength="50">
                        @error('cheque_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="cheque_name" class="form-label">Name</label>
                        <input type="text" id="cheque_name" name="cheque_name"
                            class="form-control @error('cheque_name') is-invalid @enderror"
                            value="{{ old('cheque_name', $entry->cheque_name) }}" maxlength="150">
                        @error('cheque_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="cheque_mobile" class="form-label">Mobile</label>
                        <input type="text" id="cheque_mobile" name="cheque_mobile"
                            class="form-control @error('cheque_mobile') is-invalid @enderror"
                            value="{{ old('cheque_mobile', $entry->cheque_mobile) }}" maxlength="30">
                        @error('cheque_mobile')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="cheque_bank" class="form-label">Bank</label>
                        <input type="text" id="cheque_bank" name="cheque_bank"
                            class="form-control @error('cheque_bank') is-invalid @enderror"
                            value="{{ old('cheque_bank', $entry->cheque_bank) }}" maxlength="100">
                        @error('cheque_bank')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-4">
                        <label class="form-label">Discount</label>
                        <input type="text" id="discount" class="form-control bg-light" readonly
                            value="{{ number_format($entry->exists ? $entry->discount() : 0, 2) }}">
                        <small class="text-muted">Final amount less what was handed over.</small>
                        {{-- The guard reports on this key, which is not posted — see
                             CashEntryRequest::after(). --}}
                        @error('discount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Gold</label>
                        <input type="text" id="gold-weight" class="form-control bg-light" readonly
                            data-value="{{ $goldWeight }}"
                            value="{{ $goldWeight > 0 ? number_format($goldWeight, 3) : '' }}">
                    </div>

                    <div class="col-md-3">
                        <label for="og_estimate_id" class="form-label">OG Estimate</label>
                        <select id="og_estimate_id" name="og_estimate_id"
                            class="form-select @error('og_estimate_id') is-invalid @enderror">
                            @if ($entry->og_estimate_id)
                                <option value="{{ $entry->og_estimate_id }}" selected>
                                    {{ $entry->og_reference }}
                                </option>
                            @endif
                        </select>
                        @error('og_estimate_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Gold Amount</label>
                        <input type="text" id="gold-amount" class="form-control bg-light" readonly
                            data-value="{{ $goldAmount }}"
                            value="{{ $goldAmount > 0 ? number_format($goldAmount, 2) : '' }}">
                    </div>
                </div>

                <div class="text-center mt-4 d-flex gap-2 justify-content-center">
                    <a href="{{ route('cash-entries.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line"></i> {{ $entry->exists ? 'Update' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
    <script>
        $(function () {
            const documentsUrl = '{{ route('cash-entries.lookup.documents') }}';
            const ogUrl = '{{ route('cash-entries.lookup.og-estimates') }}';

            const $final = $('#final-amount');
            const $goldWeight = $('#gold-weight');
            const $goldAmount = $('#gold-amount');
            const $discount = $('#discount');

            const read = $el => parseFloat($el.data('value')) || 0;
            const money = n => n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            function setDisplay($el, value, decimals) {
                $el.data('value', value);
                $el.val(value ? value.toFixed(decimals) : '');
            }

            // The same rule as CashMath::discount(). Stated twice on purpose — the
            // clerk needs it as they type, the server needs it to be true — and both
            // sides are tested.
            function recompute() {
                const final = read($final);
                const settled = (parseFloat($('#cash_amount').val()) || 0)
                    + (parseFloat($('#cheque_amount').val()) || 0)
                    + read($goldAmount);

                const discount = Math.round((final - settled) * 100) / 100;

                $discount.val(money(discount));
                // Flagged here rather than only on submit: the clerk should not have
                // to save to find out they typed a digit too many.
                $discount.toggleClass('is-invalid', discount < 0);
            }

            function toggleCheque() {
                const has = (parseFloat($('#cheque_amount').val()) || 0) > 0;

                $('#cheque-block').toggleClass('d-none', !has);

                if (!has) {
                    // Cleared, so a cheque number cannot be saved against no cheque.
                    $('#cheque_number, #cheque_name, #cheque_mobile, #cheque_bank').val('');
                }
            }

            window.appSelect2($('#document_reference'), {
                ajax: {
                    url: documentsUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({
                        results: (data.documents || []).map(d => ({ id: d.id, text: d.text, doc: d })),
                    }),
                },
                minimumInputLength: 0,
                placeholder: 'Select Estimate or Voucher',
                allowClear: true,
            });

            $('#document_reference').on('select2:select', function (e) {
                setDisplay($final, parseFloat(e.params.data.doc.final_amount) || 0, 2);
                recompute();
            }).on('select2:clear', function () {
                setDisplay($final, 0, 2);
                recompute();
            });

            window.appSelect2($('#og_estimate_id'), {
                ajax: {
                    url: ogUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({
                        results: (data.ogEstimates || []).map(o => ({ id: o.id, text: o.text, og: o })),
                    }),
                },
                minimumInputLength: 0,
                placeholder: 'Select OG Estimate',
                allowClear: true,
            });

            $('#og_estimate_id').on('select2:select', function (e) {
                setDisplay($goldWeight, parseFloat(e.params.data.og.gold_weight) || 0, 3);
                setDisplay($goldAmount, parseFloat(e.params.data.og.gold_amount) || 0, 2);
                recompute();
            }).on('select2:clear', function () {
                setDisplay($goldWeight, 0, 3);
                setDisplay($goldAmount, 0, 2);
                recompute();
            });

            $('#cash_amount, #cheque_amount').on('input', function () {
                toggleCheque();
                recompute();
            });

            // Once on load, so the edit screen and a redisplay after a failed save
            // both come back consistent.
            toggleCheque();
            recompute();
        });
    </script>
@endpush

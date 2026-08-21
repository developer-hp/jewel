@csrf

<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Repair Form</h5>
    </div>
    <div class="card-body">
        {{-- Labels sit above their control rather than beside it. With two columns of
             label/control pairs, help text on one side pushed that side down and the
             two stopped lining up; stacked, every field owns its own height. --}}
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Ref No</label>
                {{-- Issued by the system under a lock, so it is never typed. --}}
                <input type="text" class="form-control bg-light" value="{{ $nextRef }}" readonly>
                @unless ($form->exists)
                    <small class="text-muted">Assigned on save.</small>
                @endunless
            </div>

            <div class="col-md-3">
                <label for="form_date" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="form_date" name="form_date"
                    class="form-control @error('form_date') is-invalid @enderror"
                    value="{{ old('form_date', optional($form->form_date)->toDateString() ?? today()->toDateString()) }}"
                    required>
                @error('form_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="delivery_date" class="form-label">
                    Delivery Date <span class="text-danger">*</span>
                </label>
                <input type="date" id="delivery_date" name="delivery_date"
                    class="form-control @error('delivery_date') is-invalid @enderror"
                    value="{{ old('delivery_date', optional($form->delivery_date)->toDateString()) }}" required>
                @error('delivery_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="sales_person_ids" class="form-label">
                    Sales Person <span class="text-danger">*</span>
                </label>
                <select id="sales_person_ids" name="sales_person_ids[]" class="form-select" multiple required>
                    @foreach ($salesPersons as $person)
                        <option value="{{ $person->id }}"
                            @selected(in_array($person->id, old('sales_person_ids', $chosenSalesPersons)))>
                            {{ $person->name }}@if ($person->city) ({{ $person->city }})@endif
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">One or more; the names are kept on the form.</small>
                @error('sales_person_ids')
                    <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="contact_no" class="form-label">Contact No <span class="text-danger">*</span></label>
                <input type="text" id="contact_no" name="contact_no"
                    class="form-control @error('contact_no') is-invalid @enderror"
                    value="{{ old('contact_no', $form->contact_no) }}" maxlength="30"
                    autocomplete="off" required>
                @error('contact_no')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="contact_no_alt" class="form-label">Alternate No</label>
                <input type="text" id="contact_no_alt" name="contact_no_alt" class="form-control"
                    value="{{ old('contact_no_alt', $form->contact_no_alt) }}" maxlength="30">
            </div>

            <div class="col-md-6">
                <label for="customer_name" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" id="customer_name" name="customer_name"
                    class="form-control @error('customer_name') is-invalid @enderror"
                    value="{{ old('customer_name', $form->customer_name) }}" maxlength="150"
                    autocomplete="off" required>
                {{-- Filled in when the number matches someone already known. --}}
                <small class="text-success d-none" id="customer-hint"></small>
                @error('customer_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-8">
                <label for="address" class="form-label">Address</label>
                <textarea id="address" name="address" rows="2"
                    class="form-control @error('address') is-invalid @enderror"
                    maxlength="1000">{{ old('address', $form->address) }}</textarea>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="approx_extra_charge" class="form-label">Approx Extra Charge</label>
                <input type="number" step="0.01" min="0" id="approx_extra_charge" name="approx_extra_charge"
                    class="form-control @error('approx_extra_charge') is-invalid @enderror"
                    value="{{ old('approx_extra_charge', $form->approx_extra_charge) }}">
                @error('approx_extra_charge')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="text-muted fs-13 mb-0">
                What the customer handed in. A row left blank is ignored on save; a row whose
                piece is already back in stock shows its code and cannot be removed.
            </p>
            <button type="button" class="btn btn-dark" id="repair-add-row">
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
                        <th style="width: 56%">Description</th>
                        <th style="width: 22%">Net Weight</th>
                        <th style="width: 14%">In Stock</th>
                        <th style="width: 8%"></th>
                    </tr>
                </thead>
                <tbody id="repair-body">
                    @foreach ($lines as $i => $line)
                        @include('repair-forms.partials._line-row', ['index' => $i, 'line' => $line])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<template id="repair-row-template">
    @include('repair-forms.partials._line-row', ['index' => '__INDEX__', 'line' => null])
</template>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3"
                    class="form-control @error('remarks') is-invalid @enderror"
                    maxlength="1000">{{ old('remarks', $form->remarks) }}</textarea>
                <small class="text-muted">Prints on both copies of the form.</small>
                @error('remarks')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Photo</h5>
            </div>
            <div class="card-body text-center">
                @if ($form->photoUrl())
                    <img src="{{ $form->photoUrl() }}" alt="{{ $form->reference() }}"
                        class="img-fluid rounded mb-2" style="max-height: 150px;">
                @else
                    <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2"
                        style="height: 100px;">
                        <span class="text-muted"><i class="ri-image-line fs-24 d-block mb-1"></i>No photo</span>
                    </div>
                @endif

                <input type="file" name="photo" class="form-control form-control-sm"
                    accept="image/png,image/jpeg,image/webp">
                <small class="text-muted d-block mt-1">Optional — a picture of what came in.</small>
                @error('photo')
                    <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                @enderror

                @if ($form->hasPhoto())
                    <div class="form-check mt-2 text-start">
                        <input type="hidden" name="remove_photo" value="0">
                        <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
                        <label class="form-check-label text-danger fs-13" for="remove_photo">Remove photo</label>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="mb-4 d-flex gap-2 justify-content-center">
    <a href="{{ route('repair-forms.index') }}" class="btn btn-warning">Cancel</a>

    <button type="submit" class="btn btn-dark">
        <i class="ri-save-line"></i> {{ $form->exists ? 'Update' : 'Save' }}
    </button>

    @can('repair_form.print')
        @unless ($form->exists)
            <button type="submit" name="print_after_save" value="1" class="btn btn-secondary">
                <i class="ri-printer-line"></i> Save &amp; Print
            </button>
        @endunless
    @endcan
</div>

@push('js')
    <script>
        $(function () {
            window.appSelect2('#sales_person_ids', { allowClear: false, placeholder: 'Choose sales person…' });

            // Look the number up as it is typed: a customer already on the register
            // fills in their own name and address, and a new one is added on save.
            @can('customer.view')
                const lookupUrl = '{{ route('customers.lookup') }}';
                let lastLookup = null;
                let lookupTimer = null;

                function applyCustomer(customer) {
                    const $hint = $('#customer-hint');

                    if (!customer) {
                        $hint.addClass('d-none').text('');
                        return;
                    }

                    // Never overwrite something already typed — the clerk may be
                    // correcting a name, and the register is not the authority here.
                    if (!$('#customer_name').val().trim()) {
                        $('#customer_name').val(customer.name);
                    }

                    if (!$('#address').val().trim() && customer.address) {
                        $('#address').val(customer.address);
                    }

                    $hint.removeClass('d-none').text('Known customer: ' + customer.name);
                }

                function lookupCustomer() {
                    const phone = ($('#contact_no').val() || '').replace(/\D+/g, '');

                    if (phone.length < 6 || phone === lastLookup) {
                        return;
                    }

                    lastLookup = phone;

                    $.getJSON(lookupUrl, { phone: phone })
                        .done(response => applyCustomer(response.customer))
                        .fail(() => $('#customer-hint').addClass('d-none'));
                }

                $('#contact_no').on('input', function () {
                    clearTimeout(lookupTimer);
                    lookupTimer = setTimeout(lookupCustomer, 350);
                }).on('blur', lookupCustomer);
            @endcan

            let nextIndex = {{ $lines->count() }};

            $('#repair-add-row').on('click', function () {
                $('#repair-body').append($('#repair-row-template').html().replace(/__INDEX__/g, nextIndex++));
            });

            $(document).on('click', '.repair-remove', function () {
                $(this).closest('tr').remove();
            });

            // A new form opens with one blank row ready to fill.
            if (nextIndex === 0) {
                $('#repair-add-row').trigger('click');
            }
        });
    </script>
@endpush

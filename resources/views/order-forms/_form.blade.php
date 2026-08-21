@csrf

<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Order Form</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="row">
                    <label class="col-sm-4 col-form-label text-sm-end">Ref No</label>
                    <div class="col-sm-8">
                        {{-- Issued by the system under a lock, so it is never typed. --}}
                        <input type="text" class="form-control bg-light" value="{{ $nextRef }}" readonly>
                        @unless ($form->exists)
                            <small class="text-muted">Assigned on save.</small>
                        @endunless
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <label for="form_date" class="col-sm-4 col-form-label text-sm-end">Date</label>
                    <div class="col-sm-8">
                        <input type="date" id="form_date" name="form_date"
                            class="form-control @error('form_date') is-invalid @enderror"
                            value="{{ old('form_date', optional($form->form_date)->toDateString() ?? today()->toDateString()) }}"
                            required>
                        @error('form_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <label for="contact_no" class="col-sm-4 col-form-label text-sm-end">
                        Contact No <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-8">
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="text" id="contact_no" name="contact_no"
                                    class="form-control @error('contact_no') is-invalid @enderror"
                                    value="{{ old('contact_no', $form->contact_no) }}" maxlength="30"
                                    autocomplete="off" required>
                                @error('contact_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-6">
                                <input type="text" name="contact_no_alt" class="form-control"
                                    value="{{ old('contact_no_alt', $form->contact_no_alt) }}" maxlength="30"
                                    placeholder="Alternate">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <label for="customer_name" class="col-sm-4 col-form-label text-sm-end">
                        Name <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-8">
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
                </div>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <label for="sales_person_id" class="col-sm-4 col-form-label text-sm-end">
                        Sales Person <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-8">
                        <select id="sales_person_id" name="sales_person_id"
                            class="form-select @error('sales_person_id') is-invalid @enderror" required>
                            <option value="">Select Sales Person</option>
                            @foreach ($salesPersons as $person)
                                <option value="{{ $person->id }}"
                                    @selected(old('sales_person_id', $form->sales_person_id) == $person->id)>
                                    {{ $person->name }}@if ($person->city) ({{ $person->city }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('sales_person_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <label for="address" class="col-sm-4 col-form-label text-sm-end">Address</label>
                    <div class="col-sm-8">
                        <textarea id="address" name="address" rows="2" class="form-control"
                            maxlength="1000">{{ old('address', $form->address) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <label for="delivery_date" class="col-sm-4 col-form-label text-sm-end">
                        Delivery Date <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-8">
                        <input type="date" id="delivery_date" name="delivery_date"
                            class="form-control @error('delivery_date') is-invalid @enderror"
                            value="{{ old('delivery_date', optional($form->delivery_date)->toDateString()) }}" required>
                        @error('delivery_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="text-muted fs-13 mb-0">
                Pick a piece from stock to promise it, or leave the picker empty and tick
                <strong>Make to order</strong>. Ticking the hold column reserves a stock piece;
                a piece to be made is created under <strong>Orders &rsaquo; Order Items</strong>.
                Tick <strong>Fix Rate</strong> to pin today's per-gram rate for the line's
                purity — the quotation prices from that rather than the rate of the day.
            </p>
            <button type="button" class="btn btn-dark" id="order-add-row">
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
                        <th style="width: 17%">Item</th>
                        <th style="width: 22%">Description</th>
                        <th style="width: 9%">Net Weight</th>
                        <th style="width: 8%">LC</th>
                        <th style="width: 12%">LC type</th>
                        <th style="width: 8%">OC</th>
                        <th style="width: 7%">size/pcs</th>
                        <th style="width: 7%">Held</th>
                        <th style="width: 6%">Fix Rate</th>
                        <th style="width: 4%"></th>
                    </tr>
                </thead>
                <tbody id="order-body">
                    @foreach ($lines as $i => $line)
                        @include('order-forms.partials._line-row', ['index' => $i, 'line' => $line])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<template id="order-row-template">
    @include('order-forms.partials._line-row', ['index' => '__INDEX__', 'line' => null])
</template>

<template id="order-stone-template">
    @include('order-forms.partials._stone-row', [
        'index' => '__INDEX__',
        'sIndex' => '__SINDEX__',
        'stone' => null,
        'ready' => false,
    ])
</template>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" class="form-control"
                    maxlength="1000">{{ old('remarks', $form->remarks) }}</textarea>
                <small class="text-muted">Prints on the order form.</small>
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
                <small class="text-muted d-block mt-1">Optional — a reference picture.</small>
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
    <a href="{{ route('order-forms.index') }}" class="btn btn-warning">Cancel</a>

    <button type="submit" class="btn btn-dark">
        <i class="ri-save-line"></i> {{ $form->exists ? 'Update' : 'Save' }}
    </button>

    @can('order_form.print')
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
            const puritiesByMetal = @json($puritiesByMetal);
            const lookupUrl = '{{ route('items.lookup') }}';

            let nextIndex = {{ $lines->count() }};

            // --- purity follows metal, per line ---------------------------------
            function fillPurities($select, metalId, preferred) {
                const options = puritiesByMetal[metalId] || [];

                $select.empty().append($('<option>').val('').text('Purity'));
                options.forEach(p => $select.append($('<option>').val(p.id).text(p.name)));

                if (preferred) {
                    $select.val(String(preferred));
                }
            }

            function refreshPurity($row) {
                const $purity = $row.find('.order-purity');
                fillPurities($purity, $row.find('.order-metal').val(), $purity.data('selected'));
            }

            // --- the stock picker ------------------------------------------------
            function initItemPicker($select) {
                if ($select.prop('disabled') || $select.data('select2')) {
                    return;
                }

                $select.select2({
                    width: '100%',
                    placeholder: '— to be made —',
                    allowClear: true,
                    ajax: {
                        url: lookupUrl,
                        dataType: 'json',
                        delay: 300,
                        data: params => ({ q: params.term || '' }),
                        processResults: response => ({
                            results: (response.items || []).map(item => ({
                                id: item.id,
                                text: item.code + ' — ' + item.name,
                                item: item
                            }))
                        })
                    }
                });
            }

            // Choosing a piece copies what it is onto the line, stones and all.
            $(document).on('select2:select', '.order-item', function (e) {
                const item = e.params.data.item;
                const $row = $(this).closest('tr');

                if (!item) {
                    return;
                }

                if (!$row.find('.order-description').val().trim()) {
                    $row.find('.order-description').val(item.name);
                }

                $row.find('input[name$="[net_weight]"]').val(item.net_weight);
                $row.find('.order-metal').val(item.metal_type_id || '');
                $row.find('.order-purity').data('selected', item.purity_id);
                refreshPurity($row);

                // Labour copies off the piece's making charge; the two vocabularies
                // line up, so the type maps straight across.
                if (item.making_charge) {
                    const map = { percentage: 'percentage', fixed: 'fixed', per_gram: 'per_gram' };
                    $row.find('input[name$="[lc_amount]"]').val(item.making_charge.rate);
                    $row.find('select[name$="[lc_type]"]').val(map[item.making_charge.charge_type] || 'per_gram');
                }

                // Carried on the row so the other-charges suggestion can include them.
                $row.data('extras', (item.extra_charge_1 || 0) + (item.extra_charge_2 || 0));

                loadStones($row, item.stones || []);
                refreshHoldCell($row);
                refreshOc($row);
            });

            $(document).on('select2:clear', '.order-item', function () {
                refreshHoldCell($(this).closest('tr'));
            });

            function loadStones($row, stones) {
                const index = $row.data('index');
                const $body = $row.next('.order-stone-row').find('.order-stone-body').empty();

                stones.forEach(function (stone, s) {
                    const html = $('#order-stone-template').html()
                        .replace(/__INDEX__/g, index)
                        .replace(/__SINDEX__/g, s);

                    const $stoneRow = $(html);

                    $stoneRow.find('select').val(stone.stone_master_id);
                    $stoneRow.find('input[name$="[pieces]"]').val(stone.pieces);
                    $stoneRow.find('input[name$="[weight_carat]"]').val(stone.weight_carat);
                    $stoneRow.find('input[name$="[rate]"]').val(stone.rate);
                    $stoneRow.find('input[type="checkbox"]').prop('checked', stone.deduct_from_gross);

                    $body.append($stoneRow);
                });
            }

            // --- other charges -----------------------------------------------------
            // Mirrors OrderFormLine::ocAmount() and ItemCalculator::lineAmount(); the
            // server recomputes it on save, this is only so the clerk sees it move.
            function stoneAmount($stoneRow) {
                const $option = $stoneRow.find('.order-stone-master option:selected');
                const unit = $option.data('unit');

                if (!unit) {
                    return 0;
                }

                const master = parseFloat($option.data('rate'));
                const typed = $stoneRow.find('.order-stone-rate').val();
                const rate = typed === '' ? (isNaN(master) ? 0 : master) : parseFloat(typed) || 0;

                const pieces = parseInt($stoneRow.find('.order-stone-pieces').val(), 10) || 0;
                const carat = parseFloat($stoneRow.find('.order-stone-carat').val()) || 0;

                if (unit === 'piece') return rate * pieces;
                if (unit === 'carat') return rate * carat;
                if (unit === 'gram') return rate * carat * 0.2;
                if (unit === 'fixed') return rate;

                return 0;
            }

            // A suggestion, not a rule: stones and diamonds plus the piece's extra
            // charges. Once the box is typed in it is the clerk's figure and this
            // stops writing to it, only saying what the parts came to.
            function refreshOc($row) {
                let total = 0;

                $row.next('.order-stone-row').find('tr.order-stone').each(function () {
                    total += stoneAmount($(this));
                });

                total += parseFloat($row.data('extras')) || 0;

                const $oc = $row.find('.order-oc');
                const $hint = $row.find('.order-oc-hint');

                if ($oc.data('touched') === 1 || $oc.data('touched') === '1') {
                    $hint.toggleClass('d-none', total <= 0).text('parts: ' + total.toFixed(2));

                    return;
                }

                $oc.val(total > 0 ? total.toFixed(2) : '');
                $hint.addClass('d-none');
            }

            // A stone lives in the row after its line, so walk back to find it.
            function refreshOcFromStone($stoneRow) {
                refreshOc($stoneRow.closest('.order-stone-row').prev('tr.order-row'));
            }

            $(document).on('input change', '.order-stone input, .order-stone select', function () {
                refreshOcFromStone($(this).closest('tr.order-stone'));
            });

            // Typing in the box hands it to the clerk for good.
            $(document).on('input', '.order-oc', function () {
                $(this).data('touched', '1');
                refreshOc($(this).closest('tr.order-row'));
            });

            // --- hold vs make ------------------------------------------------------
            // Only a piece that already exists can be held here; one still to be made
            // is created on the Order Items screen.
            function refreshHoldCell($row) {
                const hasItem = !!$row.find('.order-item').val();
                const making = $row.find('.order-make').is(':checked');
                const canHold = hasItem && !making;

                $row.find('.order-reserve-wrap').toggleClass('d-none', !canHold);
                $row.find('.order-make-note').toggleClass('d-none', canHold);

                if (!canHold) {
                    $row.find('.order-reserve').prop('checked', false);
                }
            }

            $(document).on('change', '.order-make', function () {
                refreshHoldCell($(this).closest('tr'));
            });

            $(document).on('change', '.order-metal', function () {
                const $row = $(this).closest('tr');
                $row.find('.order-purity').data('selected', null);
                refreshPurity($row);
            });

            // --- rows --------------------------------------------------------------
            $('#order-add-row').on('click', function () {
                const html = $('#order-row-template').html().replace(/__INDEX__/g, nextIndex);
                $('#order-body').append(html);

                const $row = $('#order-body tr.order-row[data-index="' + nextIndex + '"]');
                initItemPicker($row.find('.order-item'));
                refreshPurity($row);
                refreshHoldCell($row);
                refreshOc($row);

                nextIndex++;
                renumber();
            });

            function renumber() {
                $('#order-body tr.order-row').each(function (i) {
                    $(this).find('.order-line-no').text(i + 1);
                });
            }

            $(document).on('click', '.order-remove', function () {
                const $row = $(this).closest('tr');
                $row.next('.order-stone-row').remove();
                $row.remove();
                renumber();
            });

            $(document).on('click', '.order-add-stone', function () {
                const $body = $(this).closest('.order-stone-row').find('.order-stone-body');
                const html = $('#order-stone-template').html()
                    .replace(/__INDEX__/g, $body.data('index'))
                    .replace(/__SINDEX__/g, $body.find('tr').length);

                $body.append(html);
                refreshOc($body.closest('.order-stone-row').prev('tr.order-row'));
            });

            $(document).on('click', '.order-remove-stone', function () {
                const $stoneRow = $(this).closest('tr.order-stone');
                const $line = $stoneRow.closest('.order-stone-row').prev('tr.order-row');

                $stoneRow.remove();
                refreshOc($line);
            });

            // --- customer autofill, as on the repair form --------------------------
            @can('customer.view')
                const customerUrl = '{{ route('customers.lookup') }}';
                let lastLookup = null;
                let lookupTimer = null;

                function lookupCustomer() {
                    const phone = ($('#contact_no').val() || '').replace(/\D+/g, '');

                    if (phone.length < 6 || phone === lastLookup) {
                        return;
                    }

                    lastLookup = phone;

                    $.getJSON(customerUrl, { phone: phone }).done(function (response) {
                        const customer = response.customer;
                        const $hint = $('#customer-hint');

                        if (!customer) {
                            $hint.addClass('d-none').text('');
                            return;
                        }

                        // Never overwrite something already typed.
                        if (!$('#customer_name').val().trim()) {
                            $('#customer_name').val(customer.name);
                        }

                        if (!$('#address').val().trim() && customer.address) {
                            $('#address').val(customer.address);
                        }

                        $hint.removeClass('d-none').text('Known customer: ' + customer.name);
                    });
                }

                $('#contact_no').on('input', function () {
                    clearTimeout(lookupTimer);
                    lookupTimer = setTimeout(lookupCustomer, 350);
                }).on('blur', lookupCustomer);
            @endcan

            // --- boot --------------------------------------------------------------
            $('#order-body tr.order-row').each(function () {
                const $row = $(this);
                initItemPicker($row.find('.order-item'));
                refreshPurity($row);
                refreshHoldCell($row);
                refreshOc($row);
            });

            renumber();

            if (nextIndex === 0) {
                $('#order-add-row').trigger('click');
            }
        });
    </script>
@endpush

@csrf

<div class="card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Item Estimate</h5>

        {{-- The morning's figures, without leaving a half-filled form. --}}
        <x-todays-rates />
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Ref No</label>
                {{-- Issued by the system under a lock, so it is never typed. --}}
                <input type="text" class="form-control bg-light" value="{{ $nextRef }}" readonly>
                @unless ($estimate->exists)
                    <small class="text-muted">Assigned on save.</small>
                @endunless
            </div>

            <div class="col-md-2">
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

            <div class="col-md-2">
                <label for="contact_no" class="form-label">Mobile</label>
                <input type="text" id="contact_no" name="contact_no" inputmode="tel"
                    class="form-control @error('contact_no') is-invalid @enderror"
                    value="{{ old('contact_no', $estimate->contact_no) }}" maxlength="30" autocomplete="off">
                @error('contact_no')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
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

            <div class="col-md-3">
                <label for="address" class="form-label">Address</label>
                <textarea id="address" name="address" rows="1" class="form-control"
                    maxlength="1000">{{ old('address', $estimate->address) }}</textarea>
            </div>

            <div class="col-md-3">
                <label for="og_estimate_id" class="form-label">OG Estimate</label>
                <select id="og_estimate_id" name="og_estimate_id" class="form-select">
                    <option value="">None</option>
                    @foreach ($ogEstimates as $og)
                        <option value="{{ $og->id }}"
                            @selected(old('og_estimate_id', $estimate->og_estimate_id) == $og->id)>
                            {{ $og->reference() }} — {{ $og->customer_name }}
                            ({{ number_format($og->totals()->value, 0) }})
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Optional — its document prints as a further page.</small>
            </div>

            <div class="col-md-3">
                <label for="load_order" class="form-label">Load from order</label>
                <div class="input-group">
                    <select id="load_order" class="form-select">
                        <option value="">Select an order</option>
                        @foreach ($orderForms as $form)
                            <option value="{{ $form->id }}"
                                @selected(old('order_form_id', $estimate->order_form_id) == $form->id)>
                                {{ trim(\App\Models\OrderForm::refPrefix().' '.$form->ref_no) }} — {{ $form->customer_name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-secondary" id="load-order-btn">Load</button>
                </div>
                <input type="hidden" name="order_form_id" id="order_form_id"
                    value="{{ old('order_form_id', $estimate->order_form_id) }}">
                <small class="text-muted">Replaces the grid; everything stays editable.</small>
            </div>

            <div class="col-md-3">
                <div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="gst_enabled" value="0">
                        <input class="form-check-input" type="checkbox" id="gst_enabled" name="gst_enabled" value="1"
                            @checked(old('gst_enabled', $estimate->gst_enabled))>
                        <label class="form-check-label" for="gst_enabled">
                            GST ? <span class="text-muted fs-12">({{ rtrim(rtrim(number_format((float) $gstPercent, 2), '0'), '.') }}%)</span>
                        </label>
                    </div>

                    <div class="form-check form-switch">
                        <input type="hidden" name="show_photo" value="0">
                        <input class="form-check-input" type="checkbox" id="show_photo" name="show_photo" value="1"
                            @checked(old('show_photo', $estimate->show_photo))>
                        <label class="form-check-label" for="show_photo">Show photo on print</label>
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
                Rate is per ten grams. Net weight is gross less the stones; tap the diamond
                button on GW — or press Enter in it — to enter them.
            </p>
            <button type="button" class="btn btn-dark" id="est-add-row">
                <i class="ri-add-line"></i> Add
            </button>
        </div>

        @error('lines')
            <div class="alert alert-danger py-2 fs-13">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table table-sm table-centered mb-0 estimate-grid">
                <thead class="table-light">
                    <tr>
                        <th colspan="2" class="text-center">ITEMS</th>
                        <th colspan="3" class="text-center">GOLD</th>
                        <th class="text-center">JADTAR</th>
                        <th colspan="2" class="text-center">LBR</th>
                        <th class="text-center">TOTAL</th>
                        <th></th>
                    </tr>
                    <tr>
                        <th style="min-width: 190px;">ITEM</th>
                        <th style="min-width: 150px;">DESC</th>
                        <th style="min-width: 150px;">GW</th>
                        <th style="min-width: 110px;">NW</th>
                        <th style="min-width: 120px;">RATE</th>
                        <th style="min-width: 110px;">AMOUNT</th>
                        <th style="min-width: 150px;">LBR</th>
                        <th style="min-width: 110px;">OC</th>
                        <th style="min-width: 130px;">TOTAL</th>
                        <th style="min-width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="est-body">
                    @foreach ($lines as $i => $line)
                        @include('item-estimates.partials._line-row', [
                            'index' => $i,
                            'line' => $line,
                            'stoneMasters' => $stoneMasters,
                        ])
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="2" class="text-end">TOTAL</td>
                        <td id="est-t-gross">0.000</td>
                        <td id="est-t-net">0.000</td>
                        {{-- The metal value, not a sum of rates. --}}
                        <td id="est-t-metal" title="Metal value">0</td>
                        <td id="est-t-jadtar">0</td>
                        <td id="est-t-labour">0</td>
                        <td id="est-t-oc">0</td>
                        <td id="est-t-total">0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Outside the grid's sideways scroll, so the figure that matters is always on screen. --}}
<div class="row">
    <div class="col-lg-5 ms-auto">
        <div class="card">
            <div class="card-body py-2">
                <table class="table table-sm mb-0">
                    <tr>
                        <th>Amount</th>
                        <td class="text-end" id="sum-amount">0</td>
                    </tr>
                    <tr id="sum-gst-row">
                        <th>GST</th>
                        <td class="text-end" id="sum-gst">0</td>
                    </tr>
                    <tr>
                        <th>Round Off</th>
                        <td class="text-end" id="sum-round">0</td>
                    </tr>
                    <tr class="fs-16">
                        <th>TOTAL</th>
                        <td class="text-end fw-bold" id="sum-total">0</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<template id="est-row-template">
    @include('item-estimates.partials._line-row', [
        'index' => '__INDEX__',
        'line' => null,
        'stoneMasters' => $stoneMasters,
    ])
</template>

<template id="est-stone-template">
    @include('item-estimates.partials._stone-row', [
        'index' => '__INDEX__',
        'sIndex' => '__SINDEX__',
        'stone' => null,
        'stoneMasters' => $stoneMasters,
    ])
</template>

{{-- Full screen on smaller glass, so a thumb has room. --}}
<div class="modal fade" id="stone-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Stones &amp; Diamonds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="stone-modal-body"></div>
            <div class="modal-footer justify-content-between py-2">
                <div class="fs-14">
                    Total weight: <strong id="stone-total-weight">0.000</strong>
                    &nbsp;&middot;&nbsp;
                    Net weight: <strong id="stone-net-weight">0.000</strong>
                </div>
                <div>
                    <button type="button" class="btn btn-success" id="stone-add">
                        <i class="ri-add-line"></i> Add
                    </button>
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mb-4 d-flex gap-2 justify-content-center">
    <a href="{{ route('item-estimates.index') }}" class="btn btn-warning">Cancel</a>

    <button type="submit" class="btn btn-dark">
        <i class="ri-save-line"></i> {{ $estimate->exists ? 'Update' : 'Save' }}
    </button>

    @can('item_estimate.print')
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
            const lookupUrl = '{{ route('items.lookup') }}';
            const fromOrderUrl = '{{ route('item-estimates.from-order', ['order_form' => '__ID__']) }}';
            const gstPercent = {{ (float) $gstPercent }};

            let nextIndex = {{ $lines->count() }};
            let $activeRow = null;

            const stoneModal = new bootstrap.Modal('#stone-modal');

            // --- item picker -------------------------------------------------------
            function initItemPicker($select) {
                window.appSelect2($select, {
                    ajax: {
                        url: lookupUrl,
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term }),
                        processResults: data => ({
                            results: (data.items || []).map(item => ({
                                id: item.id,
                                text: item.code + ' — ' + item.name,
                                item: item,
                            })),
                        }),
                    },
                    minimumInputLength: 0,
                    placeholder: 'Select Item',
                    allowClear: true,
                });

                $select.on('select2:select', function (e) {
                    fillFromItem($(this).closest('tr'), e.params.data.item);
                });
            }

            function fillFromItem($row, item) {
                if (!item) {
                    return;
                }

                $row.find('.est-desc').val(item.description || item.name);
                $row.find('.est-gross').val(item.gross_weight);

                // Labour off the piece's making charge; the two vocabularies line up.
                if (item.making_charge) {
                    $row.find('.est-labour').val(item.making_charge.rate);
                    $row.find('.est-labour-type').val(item.making_charge.charge_type);
                }

                // Other charges are the piece's two extras.
                $row.find('.est-oc').val((item.extra_charge_1 || 0) + (item.extra_charge_2 || 0));

                loadStones($row, item.stones || []);
                refresh();
            }

            function loadStones($row, stones) {
                const index = $row.data('index');
                const $store = $row.find('.est-stone-store').empty();

                stones.forEach(function (stone, i) {
                    const html = $('#est-stone-template').html()
                        .replace(/__INDEX__/g, index)
                        .replace(/__SINDEX__/g, i);
                    const $stone = $(html);

                    $stone.find('.est-stone-master').val(stone.stone_master_id);
                    $stone.find('.est-stone-grams').val(stone.weight_grams || '');
                    $stone.find('.est-stone-carat').val(
                        stone.weight_grams ? (stone.weight_grams / CARAT_TO_GRAM).toFixed(3) : ''
                    );
                    $stone.find('.est-stone-pieces').val(stone.pieces || '');
                    $stone.find('.est-stone-rate').val(stone.rate || '');
                    // The piece's own answer to "does this come out of the gross".
                    $stone.find('.est-stone-deduct').prop('checked', !!stone.deduct_from_gross);

                    $store.append($stone);
                });

                recalcStones($store);
            }

            // Carat is what the trade quotes; grams is what the schema stores.
            const CARAT_TO_GRAM = 0.2;

            // Typing in either box fills the other. Only grams carries a name, so the
            // posted shape is unchanged and the server still derives carat from it.
            $(document).on('input', '.est-stone-carat', function () {
                const carat = parseFloat($(this).val());
                $(this).closest('.est-stone').find('.est-stone-grams')
                    .val(isNaN(carat) ? '' : (carat * CARAT_TO_GRAM).toFixed(4));
            });

            $(document).on('input', '.est-stone-grams', function () {
                const grams = parseFloat($(this).val());
                $(this).closest('.est-stone').find('.est-stone-carat')
                    .val(isNaN(grams) ? '' : (grams / CARAT_TO_GRAM).toFixed(3));
            });

            // --- stone arithmetic, mirroring ItemCalculator::lineAmount() -----------
            function stoneAmount($stone) {
                const $option = $stone.find('.est-stone-master option:selected');
                const unit = $option.data('unit');

                if (!unit) {
                    return 0;
                }

                const master = parseFloat($option.data('rate'));
                const typed = $stone.find('.est-stone-rate').val();
                const rate = typed === '' ? (isNaN(master) ? 0 : master) : parseFloat(typed) || 0;

                const pieces = parseInt($stone.find('.est-stone-pieces').val(), 10) || 0;
                const grams = parseFloat($stone.find('.est-stone-grams').val()) || 0;

                if (unit === 'piece') return rate * pieces;
                if (unit === 'gram') return rate * grams;
                if (unit === 'carat') return rate * (grams / 0.2);
                if (unit === 'fixed') return rate;

                return 0;
            }

            function recalcStones($store) {
                // deducted is what comes off the gross; total is every stone on the
                // piece. The footer shows both, and they differ whenever a stone is
                // set not to deduct.
                let deducted = 0, total = 0, amount = 0;

                $store.find('.est-stone').each(function () {
                    const $stone = $(this);
                    const a = stoneAmount($stone);

                    $stone.find('.est-stone-amount').val(a ? a.toFixed(0) : '');

                    // A stone the piece does not deduct must not shrink the net
                    // weight here either — ItemCalculator::deductibleGrams() filters
                    // the same way.
                    const grams = parseFloat($stone.find('.est-stone-grams').val()) || 0;
                    total += grams;

                    if ($stone.find('.est-stone-deduct').is(':checked')) {
                        deducted += grams;
                    }

                    amount += a;
                });

                const $row = $store.closest('tr');

                $row.find('.est-jadtar').val(amount ? amount.toFixed(0) : '');
                $row.data('stone-weight', deducted);

                return { weight: deducted, total: total, amount: amount };
            }

            // --- the grid ----------------------------------------------------------
            function lineFigures($row) {
                const gross = parseFloat($row.find('.est-gross').val()) || 0;
                const stoneWeight = parseFloat($row.data('stone-weight')) || 0;
                const net = Math.round((gross - stoneWeight) * 1000) / 1000;
                const rate = parseFloat($row.find('.est-rate').val()) || 0;
                const metal = Math.round(net * rate / 10 * 100) / 100;
                const jadtar = parseFloat($row.find('.est-jadtar').val()) || 0;
                const lbr = parseFloat($row.find('.est-labour').val()) || 0;
                const oc = parseFloat($row.find('.est-oc').val()) || 0;

                let labour;
                switch ($row.find('.est-labour-type').val()) {
                    case 'percentage': labour = metal * lbr / 100; break;
                    case 'fixed': labour = lbr; break;
                    default: labour = net * lbr;
                }

                labour = Math.round(labour * 100) / 100;

                return {
                    gross: gross, net: net, metal: metal, jadtar: jadtar,
                    labour: labour, oc: oc,
                    total: Math.round((metal + jadtar + labour + oc) * 100) / 100,
                };
            }

            function refresh() {
                const t = { gross: 0, net: 0, metal: 0, jadtar: 0, labour: 0, oc: 0, total: 0 };

                $('#est-body tr.estimate-row').each(function () {
                    const $row = $(this);
                    const f = lineFigures($row);

                    $row.find('.est-net').val(f.net ? f.net.toFixed(3) : '');
                    $row.find('.est-total').val(f.total ? f.total.toFixed(2) : '');

                    Object.keys(t).forEach(k => t[k] += f[k]);
                });

                $('#est-t-gross').text(t.gross.toFixed(3));
                $('#est-t-net').text(t.net.toFixed(3));
                $('#est-t-metal').text(Math.round(t.metal).toLocaleString('en-IN'));
                $('#est-t-jadtar').text(Math.round(t.jadtar).toLocaleString('en-IN'));
                $('#est-t-labour').text(Math.round(t.labour).toLocaleString('en-IN'));
                $('#est-t-oc').text(Math.round(t.oc).toLocaleString('en-IN'));
                $('#est-t-total').text(Math.round(t.total).toLocaleString('en-IN'));

                refreshSummary(t.total);
            }

            // Mirrors ItemEstimateMath::summary(): the round-off lands on the final
            // figure, after tax, and the column always adds up.
            function refreshSummary(total) {
                const on = $('#gst_enabled').is(':checked');
                const amount = Math.round(total);
                const gst = on ? Math.round(amount * gstPercent / 100) : 0;
                const grand = Math.round((amount + gst) / 10) * 10;

                $('#sum-gst-row').toggleClass('d-none', !on);
                $('#sum-amount').text(amount.toLocaleString('en-IN'));
                $('#sum-gst').text(gst.toLocaleString('en-IN'));
                $('#sum-round').text((grand - amount - gst).toLocaleString('en-IN'));
                $('#sum-total').text(grand.toLocaleString('en-IN'));
            }

            // --- the stone popup ---------------------------------------------------
            function openStones($row) {
                $activeRow = $row;
                // Moved rather than copied, so the input names never change.
                $('#stone-modal-body').append($row.find('.est-stone-store'));
                refreshStoneFooter();
                stoneModal.show();
            }

            function refreshStoneFooter() {
                if (!$activeRow) {
                    return;
                }

                const $store = $('#stone-modal-body .est-stone-store');
                const { weight, total } = recalcStones($store);
                const gross = parseFloat($activeRow.find('.est-gross').val()) || 0;

                $('#stone-total-weight').text(total.toFixed(3));
                $('#stone-net-weight').text((Math.round((gross - weight) * 1000) / 1000).toFixed(3));
            }

            $('#stone-modal').on('hidden.bs.modal', function () {
                if ($activeRow) {
                    // Back into its own row, so it saves with the form.
                    $activeRow.find('td').last().append($('#stone-modal-body .est-stone-store'));
                    // Only now can recalcStones reach the <tr> to write stone-weight
                    // onto it — while the popup was open the store was detached, so
                    // everything edited in there is still missing from the grid.
                    recalcStones($activeRow.find('.est-stone-store'));
                    $activeRow = null;
                }

                refresh();
            });

            $('#stone-add').on('click', function () {
                const $store = $('#stone-modal-body .est-stone-store');
                const html = $('#est-stone-template').html()
                    .replace(/__INDEX__/g, $store.data('index'))
                    .replace(/__SINDEX__/g, $store.find('.est-stone').length);

                $store.append(html);
                refreshStoneFooter();
            });

            $(document).on('click', '.est-stone-remove', function () {
                $(this).closest('.est-stone').remove();
                refreshStoneFooter();
            });

            $(document).on('input change', '#stone-modal-body input, #stone-modal-body select', refreshStoneFooter);

            $(document).on('click', '.est-stones-open', function () {
                openStones($(this).closest('tr'));
            });

            // Enter in GW opens the same popup, for whoever is at a desk.
            $(document).on('keydown', '.est-gross', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    openStones($(this).closest('tr'));
                }
            });

            // --- rows --------------------------------------------------------------
            $('#est-add-row').on('click', function () {
                const html = $('#est-row-template').html().replace(/__INDEX__/g, nextIndex);
                const $row = $(html);

                $('#est-body').append($row);
                initItemPicker($row.find('.est-item'));

                nextIndex++;
                refresh();
            });

            $(document).on('click', '.est-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            $(document).on('input change', '#est-body input, #est-body select', refresh);
            $('#gst_enabled').on('change', refresh);

            // --- load from order ---------------------------------------------------
            $('#load-order-btn').on('click', function () {
                const id = $('#load_order').val();

                if (!id || !confirm('Replace the grid with this order’s lines?')) {
                    return;
                }

                $.getJSON(fromOrderUrl.replace('__ID__', id)).done(function (data) {
                    $('#order_form_id').val(id);

                    if (!$('#customer_name').val().trim()) {
                        $('#customer_name').val(data.customer_name || '');
                        $('#contact_no').val(data.contact_no || '');
                        $('#address').val(data.address || '');
                    }

                    $('#est-body').empty();
                    nextIndex = 0;

                    (data.lines || []).forEach(function (line) {
                        $('#est-add-row').trigger('click');

                        const $row = $('#est-body tr.estimate-row').last();

                        $row.find('.est-desc').val(line.description);
                        $row.find('.est-gross').val(line.gross_weight);
                        $row.find('.est-rate').val(line.rate);
                        $row.find('.est-labour').val(line.labour_amount);
                        $row.find('.est-labour-type').val(line.labour_type);
                        $row.find('.est-oc').val(line.oc_amount);

                        if (line.item_id) {
                            $row.find('.est-item').append(
                                new Option(line.description, line.item_id, true, true)
                            ).trigger('change');
                        }

                        loadStones($row, line.stones || []);
                    });

                    refresh();
                });
            });

            // --- boot --------------------------------------------------------------
            $('#est-body tr.estimate-row').each(function () {
                const $row = $(this);

                initItemPicker($row.find('.est-item'));
                recalcStones($row.find('.est-stone-store'));
            });

            if (nextIndex === 0) {
                $('#est-add-row').trigger('click');
            }

            refresh();
        });
    </script>
@endpush

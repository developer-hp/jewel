@extends('layouts.app')

@section('title', 'Add Items — ' . $lot->code)

@php
    // Rows survive a rejected save: the queue is rebuilt from old input rather than
    // being thrown away with the typing in it.
    $queued = old('rows', []);
    $groupNames = $groups->pluck('name', 'id');
    $stoneOptions = $stoneMasters->map(fn ($m) => [
        'id' => $m->id, 'name' => $m->name, 'unit' => $m->rate_unit, 'rate' => (float) $m->default_rate,
    ])->values();
    $diamondOptions = $diamondMasters->map(fn ($m) => [
        'id' => $m->id, 'name' => $m->name, 'unit' => $m->rate_unit, 'rate' => (float) $m->default_rate,
    ])->values();
@endphp

@section('content')
    <x-page-title :title="'Add Items — ' . $lot->code">
        <x-slot:actions>
            <a href="{{ route('lots.show', $lot) }}" class="btn btn-light">Back to Lot</a>
        </x-slot:actions>
    </x-page-title>

    @error('rows')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>{{ $errors->count() }} problem(s) with the batch.</strong> Nothing was saved — your rows are
            still below.
            <ul class="mb-0 mt-1 fs-13">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('lots.items.store', $lot) }}" id="entry-form">
        @csrf

        {{-- Defaults seeded into each new row. Supplier is a batch fact and posts as-is. --}}
        <div class="card">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label for="d-metal" class="form-label mb-1 fs-12">Metal (default)</label>
                        <select id="d-metal" class="form-select form-select-sm">
                            <option value="">Select…</option>
                            @foreach ($metalTypes as $metalType)
                                <option value="{{ $metalType->id }}" @selected($lot->metal_type_id == $metalType->id)>
                                    {{ $metalType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="d-purity" class="form-label mb-1 fs-12">Purity (default)</label>
                        <select id="d-purity" class="form-select form-select-sm">
                            <option value="">Select metal first</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="d-making" class="form-label mb-1 fs-12">Making (default)</label>
                        <select id="d-making" class="form-select form-select-sm">
                            <option value="">None</option>
                            @foreach ($makingCharges as $charge)
                                <option value="{{ $charge->id }}" @selected($lot->making_charge_id == $charge->id)>
                                    {{ $charge->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="supplier_id" class="form-label mb-1 fs-12">Supplier (whole batch)</label>
                        <select id="supplier_id" name="supplier_id" class="form-select form-select-sm">
                            <option value="">None — in-house</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    @selected(old('supplier_id', $lot->supplier_id) == $supplier->id)>
                                    {{ $supplier->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 text-end">
                        <div class="fs-12 text-muted">Remaining</div>
                        <div class="fs-18 fw-semibold"><span id="remaining-total">0</span> tags</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- The entry row. Enter walks the fields; Enter on the last one queues it. --}}
        <div class="card border-primary">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end" id="entry-row">
                    <div class="col-md-2">
                        <label class="form-label mb-1 fs-12">Group</label>
                        <select class="form-select form-select-sm entry-field" id="e-group">
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" data-name="{{ $group->name }}">
                                    {{ $group->name }} ({{ $group->prefix }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 fs-12">Name</label>
                        <input type="text" class="form-control form-control-sm entry-field" id="e-name" maxlength="150">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fs-12">Metal</label>
                        <select class="form-select form-select-sm entry-field" id="e-metal">
                            @foreach ($metalTypes as $metalType)
                                <option value="{{ $metalType->id }}">{{ $metalType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fs-12">Purity</label>
                        <select class="form-select form-select-sm entry-field" id="e-purity"></select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fs-12">Making</label>
                        <select class="form-select form-select-sm entry-field" id="e-making">
                            <option value="">None</option>
                            @foreach ($makingCharges as $charge)
                                <option value="{{ $charge->id }}">{{ $charge->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fs-12">HUID</label>
                        <input type="text" class="form-control form-control-sm entry-field text-uppercase" id="e-huid"
                            maxlength="20">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fs-12">Gross</label>
                        <input type="number" step="0.001" min="0" class="form-control form-control-sm entry-field"
                            id="e-gross">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fs-12">Less</label>
                        <input type="number" step="0.001" min="0" class="form-control form-control-sm entry-field"
                            id="e-less" value="0">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fs-12">Net</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="e-net" value="0.000"
                            readonly tabindex="-1">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-info flex-grow-1" id="open-stones"
                            title="Stones and diamonds (F4)">
                            <i class="ri-shining-2-fill"></i> <span id="stone-count-badge">0</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="add-row" title="Add row">
                            <i class="ri-add-line"></i>
                        </button>
                    </div>
                </div>

                <div class="fs-12 text-muted mt-2">
                    <kbd>Enter</kbd> next field · <kbd>Enter</kbd> on Less adds the row ·
                    <kbd>F4</kbd> stones · <kbd>Esc</kbd> clears · <kbd>F9</kbd> saves all
                    <span class="ms-2" id="remaining-detail"></span>
                </div>
                <div class="text-danger fs-13 mt-1 d-none" id="entry-error"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h5 class="mb-0">Queued Rows (<span id="queue-count">0</span>)</h5>
                <span class="badge bg-warning-subtle text-warning" id="unsaved-badge">Not saved yet</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0" id="queue-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 4%">#</th>
                                <th>Group</th>
                                <th>Name</th>
                                <th>Metal / Purity</th>
                                <th>MC</th>
                                <th>HUID</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Stones</th>
                                <th class="text-end">Net</th>
                                <th style="width: 8%"></th>
                            </tr>
                        </thead>
                        <tbody id="queue-body"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-end">Total</th>
                                <th class="text-end"><span id="total-gross">0.000</span></th>
                                <th></th>
                                <th class="text-end"><span id="total-net">0.000</span></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="text-muted fs-13 mb-0 mt-2" id="queue-empty">
                    No rows queued. Fill the row above and press Enter.
                </p>

                @if ($lot->total_gross_weight !== null)
                    <div class="alert alert-warning py-2 fs-13 mt-2 d-none" id="target-warning">
                        <i class="ri-alert-line me-1"></i>
                        These rows plus the {{ number_format($lot->grossEntered(), 3) }} g already entered exceed
                        the lot's declared {{ number_format((float) $lot->total_gross_weight, 3) }} g.
                        You can still save.
                    </div>
                @endif
            </div>
        </div>

        <div class="mb-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary" id="save-all" disabled>
                <i class="ri-save-line"></i> Save All <span class="ms-1 opacity-75">(F9)</span>
            </button>
            <a href="{{ route('lots.show', $lot) }}" class="btn btn-light">Cancel</a>
        </div>

        {{-- Hidden inputs for the whole queue are written here on submit. --}}
        <div id="queue-inputs"></div>
    </form>

    @include('lots.partials.stone-modal')
@endsection

@push('js')
    <script>
        $(function () {
            const puritiesByMetal = @js($puritiesByMetal);
            const groupNames = @js($groupNames);
            const stoneOptions = { stone: @js($stoneOptions), diamond: @js($diamondOptions) };
            const makingLabels = @js($makingCharges->pluck('code', 'id'));
            const metalLabels = @js($metalTypes->pluck('name', 'id'));
            const CARAT_TO_GRAM = {{ \App\Models\Item::CARAT_TO_GRAM }};

            const remainingStart = @js((object) $remaining);
            const alreadyGross = {{ $lot->grossEntered() }};
            const targetGross = {{ $lot->total_gross_weight !== null ? (float) $lot->total_gross_weight : 'null' }};

            let queue = [];
            let entryStones = [];      // stones being built for the row in the entry line
            let editingIndex = null;   // queued row whose stones the popup is editing
            let saving = false;

            const stoneModal = new bootstrap.Modal(document.getElementById('row-stone-modal'));

            // --- purity follows metal, on the defaults strip and on the row --------
            function fillPurities($select, metalId, keep) {
                const list = puritiesByMetal[metalId] || [];
                const previous = keep ? $select.val() : null;

                $select.empty().append($('<option>').val('').text(list.length ? 'Select…' : 'No purities'));
                list.forEach(p => $select.append($('<option>').val(p.id).text(p.name)));

                if (previous) { $select.val(previous); }
            }

            function purityName(metalId, purityId) {
                const found = (puritiesByMetal[metalId] || []).find(p => String(p.id) === String(purityId));

                return found ? found.name : '';
            }

            // --- remaining tags, less whatever is already queued -------------------
            function remainingFor(groupId) {
                const base = remainingStart[groupId] || 0;

                return base - queue.filter(r => String(r.item_group_id) === String(groupId)).length;
            }

            function renderRemaining() {
                let total = 0;
                const parts = [];

                Object.keys(remainingStart).forEach(function (groupId) {
                    const left = remainingFor(groupId);
                    total += left;
                    parts.push((groupNames[groupId] || 'Group') + ' ' + left);
                });

                $('#remaining-total').text(total);
                $('#remaining-detail').text(parts.length ? '· ' + parts.join(' · ') + ' left' : '');
            }

            // --- stone maths, mirroring App\Services\ItemCalculator ----------------
            function stoneGrams(stone) {
                let carat = parseFloat(stone.weight_carat) || 0;
                if (carat <= 0) { carat = (parseFloat(stone.weight_grams) || 0) / CARAT_TO_GRAM; }

                return carat * CARAT_TO_GRAM;
            }

            function stoneAmount(stone) {
                const carat = parseFloat(stone.weight_carat) || 0;
                const grams = stoneGrams(stone);
                const rate = parseFloat(stone.rate) || 0;

                if (stone.unit === 'piece') return rate * (parseInt(stone.pieces, 10) || 0);
                if (stone.unit === 'carat') return rate * carat;
                if (stone.unit === 'gram') return rate * grams;
                if (stone.unit === 'fixed') return rate;

                return 0;
            }

            function deductedGrams(stones) {
                return stones.filter(s => s.deduct_from_gross).reduce((sum, s) => sum + stoneGrams(s), 0);
            }

            function rowNet(row) {
                return row.gross_weight - deductedGrams(row.stones) - row.other_deduction;
            }

            // --- the queue ---------------------------------------------------------
            function render() {
                const $body = $('#queue-body').empty();
                let gross = 0, net = 0;

                queue.forEach(function (row, i) {
                    const n = rowNet(row);
                    gross += row.gross_weight;
                    net += n;

                    const stoneLabel = row.stones.length
                        ? row.stones.length + ' · ' + deductedGrams(row.stones).toFixed(3) + 'g'
                        : '—';

                    $body.append(
                        $('<tr>').append(
                            $('<td>').text(i + 1),
                            $('<td>').text(groupNames[row.item_group_id] || ''),
                            $('<td>').text(row.name),
                            $('<td>').text((metalLabels[row.metal_type_id] || '') + ' / ' + purityName(row.metal_type_id, row.purity_id)),
                            $('<td>').text(makingLabels[row.making_charge_id] || '—'),
                            $('<td>').text(row.huid || '—'),
                            $('<td>').addClass('text-end').text(row.gross_weight.toFixed(3)),
                            $('<td>').addClass('text-end').append(
                                $('<button type="button" class="btn btn-sm btn-link p-0 edit-stones">')
                                    .attr('data-index', i).text(stoneLabel)
                            ),
                            $('<td>').addClass('text-end fw-semibold').text(n.toFixed(3)),
                            $('<td>').addClass('text-end').append(
                                $('<button type="button" class="btn btn-sm btn-danger btn-icon remove-queued">')
                                    .attr('data-index', i).attr('title', 'Remove')
                                    .append('<i class="ri-delete-bin-2-fill"></i>')
                            )
                        )
                    );
                });

                writeInputs();

                $('#queue-count').text(queue.length);
                $('#total-gross').text(gross.toFixed(3));
                $('#total-net').text(net.toFixed(3));
                $('#queue-empty').toggleClass('d-none', queue.length > 0);
                $('#unsaved-badge').toggleClass('d-none', queue.length === 0);
                $('#save-all').prop('disabled', queue.length === 0);

                if (targetGross !== null) {
                    $('#target-warning').toggleClass('d-none', (alreadyGross + gross) <= targetGross);
                }

                renderRemaining();
            }

            /** The queue posts as plain hidden inputs — no AJAX anywhere. */
            function writeInputs() {
                const $box = $('#queue-inputs').empty();

                function hidden(name, value) {
                    $box.append($('<input type="hidden">').attr('name', name).val(value));
                }

                queue.forEach(function (row, i) {
                    const p = 'rows[' + i + ']';
                    hidden(p + '[item_group_id]', row.item_group_id);
                    hidden(p + '[metal_type_id]', row.metal_type_id);
                    hidden(p + '[purity_id]', row.purity_id);
                    hidden(p + '[making_charge_id]', row.making_charge_id || '');
                    hidden(p + '[name]', row.name);
                    hidden(p + '[huid]', row.huid);
                    hidden(p + '[gross_weight]', row.gross_weight);
                    hidden(p + '[other_deduction]', row.other_deduction);

                    row.stones.forEach(function (stone, j) {
                        const sp = p + '[stones][' + j + ']';
                        hidden(sp + '[stone_master_id]', stone.stone_master_id);
                        hidden(sp + '[pieces]', stone.pieces);
                        hidden(sp + '[weight_carat]', stone.weight_carat);
                        hidden(sp + '[weight_grams]', stone.weight_grams);
                        hidden(sp + '[rate]', stone.rate);
                        hidden(sp + '[deduct_from_gross]', stone.deduct_from_gross ? 1 : 0);
                    });
                });
            }

            function showError(message) { $('#entry-error').text(message).removeClass('d-none'); }
            function clearError() { $('#entry-error').addClass('d-none').text(''); }

            function refreshNet() {
                const gross = parseFloat($('#e-gross').val()) || 0;
                const less = parseFloat($('#e-less').val()) || 0;
                $('#e-net').val((gross - less - deductedGrams(entryStones)).toFixed(3));
                $('#stone-count-badge').text(entryStones.length);
            }

            function applyGroupName() {
                $('#e-name').val($('#e-group').find('option:selected').data('name') || '');
            }

            function resetEntry(keepGroup) {
                if (! keepGroup) { $('#e-group').prop('selectedIndex', 0); }
                $('#e-huid').val('');
                $('#e-gross').val('');
                $('#e-less').val('0');
                entryStones = [];
                applyGroupName();
                refreshNet();
                clearError();
            }

            function addRow() {
                const groupId = $('#e-group').val();
                const name = $('#e-name').val().trim();
                const metalId = $('#e-metal').val();
                const purityId = $('#e-purity').val();
                const gross = parseFloat($('#e-gross').val());
                const less = parseFloat($('#e-less').val()) || 0;

                if (! groupId) { showError('Pick a group.'); return; }
                if (! name) { showError('Item name is required.'); $('#e-name').focus(); return; }
                if (! metalId || ! purityId) { showError('Metal and purity are required.'); $('#e-metal').focus(); return; }
                if (isNaN(gross) || gross <= 0) { showError('Gross weight must be more than zero.'); $('#e-gross').focus(); return; }

                const net = gross - less - deductedGrams(entryStones);
                if (net <= 0) { showError('Deductions exceed the gross weight.'); $('#e-gross').focus(); return; }

                if (remainingFor(groupId) <= 0) {
                    showError('No tags left for ' + (groupNames[groupId] || 'this group') + '.');
                    return;
                }

                queue.push({
                    item_group_id: groupId,
                    metal_type_id: metalId,
                    purity_id: purityId,
                    making_charge_id: $('#e-making').val(),
                    name: name,
                    huid: $('#e-huid').val().trim().toUpperCase(),
                    gross_weight: gross,
                    other_deduction: less,
                    stones: entryStones,
                });

                render();
                // Group, metal, purity and making stay put so a run of the same piece
                // is pure typing.
                resetEntry(true);
                $('#e-name').focus().select();
            }

            // --- stone popup --------------------------------------------------------
            function stoneRowHtml(kind, stone, index) {
                const options = stoneOptions[kind].map(function (m) {
                    const selected = stone && String(stone.stone_master_id) === String(m.id) ? ' selected' : '';

                    return '<option value="' + m.id + '" data-unit="' + m.unit + '" data-rate="' + m.rate + '"' +
                        selected + '>' + $('<div>').text(m.name).html() + '</option>';
                }).join('');

                return '<tr class="ms-row" data-kind="' + kind + '">' +
                    '<td><select class="form-select form-select-sm ms-master"><option value="">Select…</option>' + options + '</select></td>' +
                    '<td><input type="number" min="0" step="1" class="form-control form-control-sm ms-pieces" value="' + (stone ? stone.pieces : 0) + '"></td>' +
                    '<td><input type="number" min="0" step="0.001" class="form-control form-control-sm ms-carat" value="' + (stone ? stone.weight_carat : 0) + '"></td>' +
                    '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm ms-grams" value="' + (stone ? stone.weight_grams : 0) + '"></td>' +
                    '<td><span class="ms-unit badge bg-secondary-subtle text-secondary">—</span></td>' +
                    '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm ms-rate" value="' + (stone && stone.rate !== null ? stone.rate : '') + '" placeholder="master"></td>' +
                    '<td class="text-end"><span class="ms-amount fw-semibold">0.00</span></td>' +
                    '<td class="text-center"><input type="checkbox" class="form-check-input ms-deduct"' + (! stone || stone.deduct_from_gross ? ' checked' : '') + '></td>' +
                    '<td class="text-end"><button type="button" class="btn btn-sm btn-danger btn-icon ms-remove"><i class="ri-delete-bin-2-fill"></i></button></td>' +
                    '</tr>';
            }

            function openStones(index) {
                editingIndex = index;
                const stones = index === null ? entryStones : queue[index].stones;

                $('#ms-stone-body, #ms-diamond-body').empty();
                stones.forEach(function (stone) {
                    $(stone.kind === 'diamond' ? '#ms-diamond-body' : '#ms-stone-body')
                        .append(stoneRowHtml(stone.kind, stone));
                });

                $('#ms-target').text(index === null ? 'the row being entered' : 'queued row ' + (index + 1));
                refreshModal();
                stoneModal.show();
            }

            function refreshModal() {
                let grams = 0, amount = 0;

                $('#row-stone-modal .ms-row').each(function () {
                    const $row = $(this);
                    const $opt = $row.find('.ms-master option:selected');
                    const unit = $opt.data('unit') || '';
                    const masterRate = parseFloat($opt.data('rate'));
                    const $rate = $row.find('.ms-rate');

                    $rate.attr('placeholder', isNaN(masterRate) ? 'master' : masterRate);

                    const stone = {
                        unit: unit,
                        pieces: $row.find('.ms-pieces').val(),
                        weight_carat: $row.find('.ms-carat').val(),
                        weight_grams: $row.find('.ms-grams').val(),
                        rate: $rate.val() === '' ? (isNaN(masterRate) ? 0 : masterRate) : $rate.val(),
                    };

                    const g = stoneGrams(stone);
                    const a = stoneAmount(stone);

                    $row.find('.ms-unit').text(unit || '—');
                    $row.find('.ms-amount').text(a.toFixed(2));

                    if ($row.find('.ms-deduct').is(':checked')) { grams += g; }
                    amount += a;
                });

                $('#ms-total-grams').text(grams.toFixed(4));
                $('#ms-total-amount').text(amount.toFixed(2));
            }

            function collectStones() {
                const stones = [];

                $('#row-stone-modal .ms-row').each(function () {
                    const $row = $(this);
                    const masterId = $row.find('.ms-master').val();

                    if (! masterId) { return; }

                    const $opt = $row.find('.ms-master option:selected');
                    const masterRate = parseFloat($opt.data('rate'));
                    const rateVal = $row.find('.ms-rate').val();

                    stones.push({
                        kind: $row.data('kind'),
                        stone_master_id: masterId,
                        unit: $opt.data('unit'),
                        pieces: parseInt($row.find('.ms-pieces').val(), 10) || 0,
                        weight_carat: parseFloat($row.find('.ms-carat').val()) || 0,
                        weight_grams: parseFloat($row.find('.ms-grams').val()) || 0,
                        rate: rateVal === '' ? (isNaN(masterRate) ? 0 : masterRate) : parseFloat(rateVal),
                        deduct_from_gross: $row.find('.ms-deduct').is(':checked'),
                    });
                });

                return stones;
            }

            $('#ms-apply').on('click', function () {
                const stones = collectStones();

                if (editingIndex === null) {
                    entryStones = stones;
                    refreshNet();
                } else {
                    queue[editingIndex].stones = stones;
                    render();
                }

                stoneModal.hide();
            });

            $('#row-stone-modal').on('click', '.ms-add', function () {
                const kind = $(this).data('kind');
                $('#ms-' + kind + '-body').append(stoneRowHtml(kind, null));
                refreshModal();
            });

            $('#row-stone-modal').on('click', '.ms-remove', function () {
                $(this).closest('tr').remove();
                refreshModal();
            });

            // Carat and gram mirror each other, as on the item form.
            let syncing = false;

            $('#row-stone-modal').on('input', '.ms-carat', function () {
                if (syncing) return;
                syncing = true;
                const carat = parseFloat($(this).val());
                $(this).closest('tr').find('.ms-grams').val(isNaN(carat) ? '' : (carat * CARAT_TO_GRAM).toFixed(4));
                syncing = false;
                refreshModal();
            });

            $('#row-stone-modal').on('input', '.ms-grams', function () {
                if (syncing) return;
                syncing = true;
                const grams = parseFloat($(this).val());
                $(this).closest('tr').find('.ms-carat').val(isNaN(grams) ? '' : (grams / CARAT_TO_GRAM).toFixed(3));
                syncing = false;
                refreshModal();
            });

            $('#row-stone-modal').on('input change', 'input, select', refreshModal);

            // --- keyboard ------------------------------------------------------------
            const order = ['#e-group', '#e-name', '#e-metal', '#e-purity', '#e-making', '#e-huid', '#e-gross', '#e-less'];

            $('#entry-row').on('keydown', '.entry-field', function (event) {
                if (event.key === 'Enter') {
                    // Enter must never submit; only Save All does.
                    event.preventDefault();

                    const index = order.indexOf('#' + this.id);

                    if (index === order.length - 1) {
                        addRow();
                    } else {
                        $(order[index + 1]).focus().select();
                    }
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    resetEntry(false);
                    $('#e-group').focus();
                }
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'F4') {
                    event.preventDefault();
                    openStones(null);
                }

                if (event.key === 'F9' || (event.ctrlKey && event.key.toLowerCase() === 's')) {
                    event.preventDefault();
                    if (queue.length > 0) { saving = true; $('#entry-form').submit(); }
                }
            });

            // A stray Enter anywhere else in the form must not submit it either.
            $('#entry-form').on('keydown', 'input, select', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); }
            });

            $('#open-stones').on('click', () => openStones(null));
            $(document).on('click', '.edit-stones', function () { openStones($(this).data('index')); });

            $(document).on('click', '.remove-queued', function () {
                queue.splice($(this).data('index'), 1);
                render();
            });

            $('#add-row').on('click', addRow);
            $('#e-gross, #e-less').on('input', refreshNet);
            $('#e-group').on('change', function () { applyGroupName(); clearError(); });
            $('#e-metal').on('change', function () { fillPurities($('#e-purity'), $(this).val(), false); });

            // Changing a default reseeds the entry row, without touching queued rows.
            $('#d-metal').on('change', function () {
                fillPurities($('#d-purity'), $(this).val(), false);
                $('#e-metal').val($(this).val()).trigger('change');
            });
            $('#d-purity').on('change', function () { $('#e-purity').val($(this).val()); });
            $('#d-making').on('change', function () { $('#e-making').val($(this).val()); });

            // Nothing is written until Save All, so warn before the queue is lost.
            $(window).on('beforeunload', function () {
                if (queue.length > 0 && ! saving) {
                    return 'You have rows that have not been saved yet.';
                }
            });

            $('#entry-form').on('submit', function () { saving = true; });

            // Rebuild the queue after a rejected save so no typing is lost.
            @if (! empty($queued))
                queue = @js(array_values(array_map(fn ($r) => [
                    'item_group_id' => (string) ($r['item_group_id'] ?? ''),
                    'metal_type_id' => (string) ($r['metal_type_id'] ?? ''),
                    'purity_id' => (string) ($r['purity_id'] ?? ''),
                    'making_charge_id' => (string) ($r['making_charge_id'] ?? ''),
                    'name' => $r['name'] ?? '',
                    'huid' => $r['huid'] ?? '',
                    'gross_weight' => (float) ($r['gross_weight'] ?? 0),
                    'other_deduction' => (float) ($r['other_deduction'] ?? 0),
                    'stones' => array_values(array_map(fn ($s) => [
                        'kind' => 'stone',
                        'stone_master_id' => (string) ($s['stone_master_id'] ?? ''),
                        'unit' => '',
                        'pieces' => (int) ($s['pieces'] ?? 0),
                        'weight_carat' => (float) ($s['weight_carat'] ?? 0),
                        'weight_grams' => (float) ($s['weight_grams'] ?? 0),
                        'rate' => (float) ($s['rate'] ?? 0),
                        'deduct_from_gross' => (bool) ($s['deduct_from_gross'] ?? false),
                    ], $r['stones'] ?? [])),
                ], $queued)));
            @endif

            // Seed the defaults strip, then mirror it into the entry row.
            fillPurities($('#d-purity'), $('#d-metal').val(), false);
            $('#d-purity').val('{{ $lot->purity_id }}');
            $('#e-metal').val($('#d-metal').val());
            fillPurities($('#e-purity'), $('#e-metal').val(), false);
            $('#e-purity').val($('#d-purity').val());
            $('#e-making').val($('#d-making').val());

            applyGroupName();
            render();
            refreshNet();
            $('#e-name').focus();
        });
    </script>
@endpush

@extends('layouts.app')

@section('title', 'Make Piece To Order')

@include('layouts.partials.select2-assets')

@php
    $caratToGram = \App\Models\Item::CARAT_TO_GRAM;
@endphp

@section('content')
    <x-page-title title="Make Piece To Order" />

    @if ($forms->isEmpty())
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="ri-information-line me-1"></i>
                            Nothing is waiting to be made — every made-to-order line already has its
                            piece. <a href="{{ route('order-forms.index') }}">Back to order forms</a>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('order-items.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Which line is this for?</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Choosing a line fills this form from what was ordered — metal, purity,
                        weight and the stones asked for. All of it stays editable: the order was
                        approximate, the piece is what it is. Saving puts it into stock under the
                        reserved <strong>{{ $group->name }}</strong> group and holds it against the order.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="order_form" class="form-label">
                                Order No <span class="text-danger">*</span>
                            </label>
                            <select id="order_form" class="form-select" required>
                                <option value="">Select</option>
                                @foreach ($forms as $orderForm)
                                    <option value="{{ $orderForm->id }}"
                                        data-lines="{{ json_encode($orderForm->lines->map(fn ($line) => [
                                            'id' => $line->id,
                                            'description' => $line->description,
                                            'size_pcs' => $line->size_pcs,
                                            'metal_type_id' => $line->metal_type_id,
                                            'purity_id' => $line->purity_id,
                                            'net_weight' => (float) $line->net_weight,
                                            'code' => $line->item?->code,
                                            'stones' => $line->stones->map(fn ($s) => [
                                                'stone_master_id' => $s->stone_master_id,
                                                'kind' => $s->kind,
                                                'pieces' => $s->pieces,
                                                'weight_carat' => (float) $s->weight_carat,
                                                'weight_grams' => (float) $s->weight_grams,
                                                'rate' => (float) $s->rate,
                                                'deduct_from_gross' => (bool) $s->deduct_from_gross,
                                            ])->values(),
                                        ])->values()) }}"
                                        @selected(old('order_form', request('form')) == $orderForm->id)>
                                        {{ $orderForm->reference() }} — {{ $orderForm->customer_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label for="order_form_line_id" class="form-label">
                                Line <span class="text-danger">*</span>
                            </label>
                            <select id="order_form_line_id" name="order_form_line_id"
                                class="form-select @error('order_form_line_id') is-invalid @enderror" required>
                                <option value="">Choose an order number first</option>
                            </select>
                            <small class="text-muted">Lines already made show their code and cannot be chosen again.</small>
                            @error('order_form_line_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Item Code</label>
                            <input type="text" class="form-control bg-light" value="{{ $nextCode }}" readonly>
                            <small class="text-muted">On save.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header py-2">
                            <h5 class="mb-0">The Piece</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name') }}" maxlength="150" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="huid" class="form-label">HUID</label>
                                    <input type="text" id="huid" name="huid" class="form-control text-uppercase"
                                        value="{{ old('huid') }}" maxlength="20">
                                </div>

                                <div class="col-md-4">
                                    <label for="metal_type_id" class="form-label">
                                        Metal Type <span class="text-danger">*</span>
                                    </label>
                                    <select id="metal_type_id" name="metal_type_id"
                                        class="form-select @error('metal_type_id') is-invalid @enderror" required>
                                        <option value="">Select</option>
                                        @foreach ($metalTypes as $metal)
                                            <option value="{{ $metal->id }}" @selected(old('metal_type_id') == $metal->id)>
                                                {{ $metal->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('metal_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="purity_id" class="form-label">
                                        Purity <span class="text-danger">*</span>
                                    </label>
                                    <select id="purity_id" name="purity_id"
                                        class="form-select @error('purity_id') is-invalid @enderror" required>
                                        <option value="">Select</option>
                                    </select>
                                    @error('purity_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="making_charge_id" class="form-label">Making Charge</label>
                                    <select id="making_charge_id" name="making_charge_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($makingCharges as $charge)
                                            <option value="{{ $charge->id }}" @selected(old('making_charge_id') == $charge->id)>
                                                {{ $charge->code }} — {{ $charge->summary() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="gross_weight" class="form-label">
                                        Gross Weight (g) <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="0.001" min="0" id="gross_weight" name="gross_weight"
                                        class="form-control @error('gross_weight') is-invalid @enderror"
                                        value="{{ old('gross_weight') }}" required>
                                    @error('gross_weight')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="other_deduction" class="form-label">Other Deduction (g)</label>
                                    <input type="number" step="0.001" min="0" id="other_deduction"
                                        name="other_deduction" class="form-control"
                                        value="{{ old('other_deduction', 0) }}">
                                    <small class="text-muted">Wax, lac or thread.</small>
                                </div>

                                <div class="col-md-4">
                                    <label for="supplier_id" class="form-label">Karigar / Supplier</label>
                                    <select id="supplier_id" name="supplier_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                                {{ $supplier->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea id="description" name="description" rows="2" class="form-control"
                                        maxlength="1000">{{ old('description') }}</textarea>
                                </div>

                                @foreach ([1, 2] as $slot)
                                    <div class="col-md-3">
                                        <label for="extra_charge_{{ $slot }}" class="form-label">
                                            Extra Charge {{ $slot }}
                                        </label>
                                        <input type="number" step="0.01" min="0" id="extra_charge_{{ $slot }}"
                                            name="extra_charge_{{ $slot }}" class="form-control extra-charge"
                                            value="{{ old('extra_charge_'.$slot) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Label {{ $slot }}</label>
                                        <input type="text" name="extra_charge_{{ $slot }}_label" class="form-control"
                                            value="{{ old('extra_charge_'.$slot.'_label') }}" maxlength="20"
                                            placeholder="Polish, rhodium…">
                                    </div>
                                @endforeach

                                <div class="col-md-6">
                                    <label for="photo" class="form-label">Photo</label>
                                    <input type="file" id="photo" name="photo" class="form-control"
                                        accept="image/png,image/jpeg,image/webp">
                                    @error('photo')
                                        <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- The same live summary the item form shows; the shared script drives both. --}}
                <div class="col-lg-4">
                    <div class="card bg-light-subtle border">
                        <div class="card-header py-2">
                            <h5 class="mb-0">Live Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-2">
                                <tbody>
                                    <tr>
                                        <td>Gross weight</td>
                                        <td class="text-end"><span id="sum-gross">0.000</span> g</td>
                                    </tr>
                                    <tr>
                                        <td>Less: stones</td>
                                        <td class="text-end text-danger">−<span id="sum-stone">0.000</span> g</td>
                                    </tr>
                                    <tr>
                                        <td>Less: diamonds</td>
                                        <td class="text-end text-danger">−<span id="sum-diamond">0.000</span> g</td>
                                    </tr>
                                    <tr>
                                        <td>Less: other</td>
                                        <td class="text-end text-danger">−<span id="sum-other">0.000</span> g</td>
                                    </tr>
                                    <tr class="table-active">
                                        <th>Net weight</th>
                                        <th class="text-end"><span id="sum-net">0.000</span> g</th>
                                    </tr>
                                </tbody>
                            </table>

                            <div id="net-warning" class="alert alert-danger py-2 fs-13 d-none">
                                <i class="ri-error-warning-line me-1"></i>
                                Deductions exceed the gross weight — this will be rejected on save.
                            </div>

                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td>Metal value <small class="text-muted">(today's rate)</small></td>
                                        <td class="text-end">₹<span id="sum-metal">0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td>Stone + diamond value</td>
                                        <td class="text-end">₹<span id="sum-stone-value">0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td>Extra charges</td>
                                        <td class="text-end">₹<span id="sum-extra">0.00</span></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="alert alert-secondary py-2 fs-13 mt-2 mb-0" id="ordered-note">
                                Pick a line to see what was ordered.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stones and diamonds live in popups; these buttons open them and report
                 what is inside. Identical to the item form, driven by the same script. --}}
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Stones &amp; Diamonds</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ([['stone', 'Stones', 'ri-shining-2-fill'], ['diamond', 'Diamonds', 'ri-vip-diamond-fill']] as [$section, $label, $icon])
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#{{ $section }}-modal">
                                        <i class="{{ $icon }}"></i> {{ $label }}
                                    </button>
                                    <div class="text-muted fs-13" id="{{ $section }}-trigger-summary">
                                        No {{ strtolower($label) }} added
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @include('items.partials._stone-section', [
                'section' => 'stone',
                'title' => 'Stones',
                'masters' => $stoneMasters,
                'rows' => $stoneRows,
                'offset' => 0,
            ])

            @include('items.partials._stone-section', [
                'section' => 'diamond',
                'title' => 'Diamonds',
                'masters' => $diamondMasters,
                'rows' => $diamondRows,
                'offset' => 1000,
            ])

            <div class="mb-4 d-flex gap-2 justify-content-center">
                <a href="{{ route('order-forms.index') }}" class="btn btn-warning">Cancel</a>
                <button type="submit" class="btn btn-dark"><i class="ri-save-line"></i> Save</button>
                <button type="submit" name="save_and_add_another" value="1" class="btn btn-secondary">
                    <i class="ri-add-line"></i> Save &amp; Add Another
                </button>
            </div>
        </form>
    @endif
@endsection

@if ($forms->isNotEmpty())
    @include('items.partials._form-script')

    @push('js')
        <script>
            $(function () {
                // Every order's lines are already on the page, so changing the order
                // number needs no round trip.
                const oldLineId = @json(old('order_form_line_id'));
                const CARAT = {{ $caratToGram }};

                window.appSelect2('#order_form', { allowClear: false });

                function fillLines() {
                    const lines = $('#order_form option:selected').data('lines') || [];
                    const $select = $('#order_form_line_id').empty();

                    $select.append($('<option>').val('').text(lines.length ? 'Select' : 'Choose an order number first'));

                    lines.forEach(function (line, i) {
                        $select.append(
                            $('<option>')
                                .val(line.id)
                                .text((i + 1) + '. ' + line.description + (line.code ? '  (made: ' + line.code + ')' : ''))
                                .prop('disabled', !!line.code)
                                .data('line', line)
                        );
                    });

                    if (oldLineId) {
                        $select.val(oldLineId);
                    }

                    seedFromLine();
                }

                // Fill the stone or diamond popup from what the customer asked for.
                function seedStones(section, stones) {
                    const $body = $('table[data-section="' + section + '"] .stone-rows').empty();
                    const offset = section === 'diamond' ? 1000 : 0;

                    stones.forEach(function (stone, i) {
                        const html = $('#' + section + '-row-template').html()
                            .replace(/__INDEX__/g, offset + i);
                        const $row = $(html);

                        $row.find('.stone-master').val(stone.stone_master_id);
                        $row.find('.stone-pieces').val(stone.pieces);
                        $row.find('.stone-carat').val(stone.weight_carat);
                        $row.find('.stone-grams').val((stone.weight_carat * CARAT).toFixed(4));
                        $row.find('.stone-rate').val(stone.rate);
                        $row.find('.stone-deduct').prop('checked', stone.deduct_from_gross);

                        $body.append($row);
                    });

                    $('table[data-section="' + section + '"]').closest('.modal-content')
                        .find('.empty-hint').remove();
                }

                function seedFromLine() {
                    const line = $('#order_form_line_id option:selected').data('line');

                    if (!line) {
                        return;
                    }

                    $('#name').val(line.description);
                    $('#description').val(line.description);
                    $('#metal_type_id').val(line.metal_type_id || '').trigger('change');
                    $('#purity_id').val(line.purity_id || '');

                    const stones = (line.stones || []).filter(s => s.kind === 'stone');
                    const diamonds = (line.stones || []).filter(s => s.kind === 'diamond');

                    seedStones('stone', stones);
                    seedStones('diamond', diamonds);

                    // Gross is the ordered net plus whatever the stones deduct, so the
                    // net comes back out at what the customer asked for.
                    const deducted = (line.stones || [])
                        .filter(s => s.deduct_from_gross)
                        .reduce((sum, s) => sum + (s.weight_carat * CARAT), 0);

                    $('#gross_weight').val((line.net_weight + deducted).toFixed(3));
                    $('#other_deduction').val(0);

                    $('#ordered-note').html(
                        'Ordered: <strong>' + line.net_weight.toFixed(3) + ' g</strong> net'
                        + (line.size_pcs ? ' · size/pcs ' + line.size_pcs : '')
                    );

                    $('#gross_weight').trigger('input');
                }

                $('#order_form').on('change', fillLines);
                $('#order_form_line_id').on('change', seedFromLine);

                if ($('#order_form').val()) {
                    fillLines();
                }
            });
        </script>
    @endpush
@endif

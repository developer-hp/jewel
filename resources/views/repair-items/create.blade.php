@extends('layouts.app')

@section('title', 'Book Repaired Piece')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Book Repaired Piece Into Stock" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        The piece is back from the workshop. Booking it here puts it in stock under
                        the reserved <strong>{{ $group->name }}</strong> group and marks its line on
                        the repair form as done — when every line is done the form turns green.
                    </p>

                    @if ($forms->isEmpty())
                        <div class="alert alert-info mb-0">
                            <i class="ri-information-line me-1"></i>
                            Nothing is out for repair — every line on every form is already back in stock.
                            <a href="{{ route('repair-forms.index') }}">Back to repair forms</a>.
                        </div>
                    @else
                        <form method="POST" action="{{ route('repair-items.store') }}">
                            @csrf

                            <div class="row mb-3">
                                <label for="repair_form" class="col-sm-3 col-form-label text-sm-end">
                                    Order No <span class="text-danger">*</span>
                                </label>
                                <div class="col-sm-9">
                                    <select id="repair_form" class="form-select" required>
                                        <option value="">Select</option>
                                        @foreach ($forms as $repairForm)
                                            <option value="{{ $repairForm->id }}"
                                                data-lines="{{ json_encode($repairForm->lines->map(fn ($line) => [
                                                    'id' => $line->id,
                                                    'description' => $line->description,
                                                    'net_weight' => $line->net_weight !== null ? (float) $line->net_weight : null,
                                                    'code' => $line->item?->code,
                                                ])->values()) }}"
                                                @selected(old('repair_form', request('form')) == $repairForm->id)>
                                                {{ $repairForm->reference() }} — {{ $repairForm->customer_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="repair_form_line_id" class="col-sm-3 col-form-label text-sm-end">
                                    Line <span class="text-danger">*</span>
                                </label>
                                <div class="col-sm-9">
                                    <select id="repair_form_line_id" name="repair_form_line_id"
                                        class="form-select @error('repair_form_line_id') is-invalid @enderror" required>
                                        <option value="">Choose an order number first</option>
                                    </select>
                                    <small class="text-muted">
                                        Lines already back in stock show their code and cannot be chosen again.
                                    </small>
                                    @error('repair_form_line_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label text-sm-end">Item Code</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control bg-light" value="{{ $nextCode }}" readonly>
                                    <small class="text-muted">
                                        Issued by the {{ $group->name }} group on save. Change the prefix under
                                        @can('item_group.edit')
                                            <a href="{{ route('item-groups.edit', $group) }}">Masters &rsaquo; Item Groups</a>.
                                        @else
                                            Masters &rsaquo; Item Groups.
                                        @endcan
                                    </small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="metal_type_id" class="col-sm-3 col-form-label text-sm-end">
                                    Metal Type <span class="text-danger">*</span>
                                </label>
                                <div class="col-sm-4">
                                    <select id="metal_type_id" name="metal_type_id"
                                        class="form-select @error('metal_type_id') is-invalid @enderror" required>
                                        <option value="">Select</option>
                                        @foreach ($metalTypes as $metal)
                                            <option value="{{ $metal->id }}"
                                                @selected(old('metal_type_id', $defaultMetalTypeId) == $metal->id)>
                                                {{ $metal->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('metal_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <label for="purity_id" class="col-sm-2 col-form-label text-sm-end">
                                    Purity <span class="text-danger">*</span>
                                </label>
                                <div class="col-sm-3">
                                    <select id="purity_id" name="purity_id"
                                        class="form-select @error('purity_id') is-invalid @enderror" required>
                                        <option value="">Select</option>
                                    </select>
                                    @error('purity_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="gross_weight" class="col-sm-3 col-form-label text-sm-end">
                                    Gross Wt <span class="text-danger">*</span>
                                </label>
                                <div class="col-sm-4">
                                    <input type="number" step="0.001" min="0" id="gross_weight" name="gross_weight"
                                        class="form-control @error('gross_weight') is-invalid @enderror"
                                        value="{{ old('gross_weight') }}" required>
                                    @error('gross_weight')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <label for="net_weight" class="col-sm-2 col-form-label text-sm-end">
                                    Net Wt <span class="text-danger">*</span>
                                </label>
                                <div class="col-sm-3">
                                    <input type="number" step="0.001" min="0" id="net_weight" name="net_weight"
                                        class="form-control @error('net_weight') is-invalid @enderror"
                                        value="{{ old('net_weight') }}" required>
                                    @error('net_weight')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted" id="deduction-note"></small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="name" class="col-sm-3 col-form-label text-sm-end">
                                    Item Name <span class="text-danger">*</span>
                                </label>
                                <div class="col-sm-9">
                                    <input type="text" id="name" name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name') }}" maxlength="150" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="description" class="col-sm-3 col-form-label text-sm-end">Description</label>
                                <div class="col-sm-9">
                                    <textarea id="description" name="description" rows="2"
                                        class="form-control @error('description') is-invalid @enderror"
                                        maxlength="1000">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            @foreach ([1, 2] as $slot)
                                <div class="row mb-3">
                                    <label for="extra_charge_{{ $slot }}" class="col-sm-3 col-form-label text-sm-end">
                                        Extra Charge {{ $slot }}
                                    </label>
                                    <div class="col-sm-4">
                                        <input type="number" step="0.01" min="0" id="extra_charge_{{ $slot }}"
                                            name="extra_charge_{{ $slot }}" class="form-control"
                                            value="{{ old('extra_charge_'.$slot) }}" placeholder="Amount">
                                    </div>
                                    <div class="col-sm-5">
                                        <input type="text" name="extra_charge_{{ $slot }}_label" class="form-control"
                                            value="{{ old('extra_charge_'.$slot.'_label') }}" maxlength="20"
                                            placeholder="Label — polish, rhodium…">
                                    </div>
                                </div>
                            @endforeach

                            <div class="d-flex gap-2 justify-content-center">
                                <a href="{{ route('repair-forms.index') }}" class="btn btn-warning">Cancel</a>
                                <button type="submit" class="btn btn-dark">
                                    <i class="ri-save-line"></i> Save
                                </button>
                                <button type="submit" name="save_and_add_another" value="1" class="btn btn-secondary">
                                    <i class="ri-add-line"></i> Save &amp; Add Another
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(function () {
            // Every form's lines are already on the page, so changing the order number
            // needs no round trip.
            const puritiesByMetal = @json($puritiesByMetal);
            const defaultPurityId = @json($defaultPurityId);
            const oldLineId = @json(old('repair_form_line_id'));
            const oldPurityId = @json(old('purity_id'));

            window.appSelect2('#repair_form', { allowClear: false });

            function fillLines() {
                const lines = $('#repair_form option:selected').data('lines') || [];
                const $select = $('#repair_form_line_id').empty();

                $select.append($('<option>').val('').text(lines.length ? 'Select' : 'Choose an order number first'));

                lines.forEach(function (line, i) {
                    const weight = line.net_weight !== null ? ' — ' + line.net_weight : '';
                    const $option = $('<option>')
                        .val(line.id)
                        .text((i + 1) + '. ' + line.description + weight + (line.code ? '  (in stock: ' + line.code + ')' : ''))
                        .prop('disabled', !!line.code)
                        .data('line', line);

                    $select.append($option);
                });

                if (oldLineId) {
                    $select.val(oldLineId);
                }

                fillFromLine();
            }

            function fillFromLine() {
                const line = $('#repair_form_line_id option:selected').data('line');

                if (!line) {
                    return;
                }

                // Pre-fill from what was taken in; all of it stays editable.
                $('#name').val(line.description);
                $('#description').val(line.description);

                if (line.net_weight !== null) {
                    $('#gross_weight').val(line.net_weight);
                    $('#net_weight').val(line.net_weight);
                }

                refreshDeduction();
            }

            function fillPurities(preferred) {
                const metalId = $('#metal_type_id').val();
                const options = puritiesByMetal[metalId] || [];
                const $select = $('#purity_id').empty().append($('<option>').val('').text('Select'));

                options.forEach(p => $select.append($('<option>').val(p.id).text(p.name)));

                if (preferred && $select.find('option[value="' + preferred + '"]').length) {
                    $select.val(preferred);
                }
            }

            function refreshDeduction() {
                const gross = parseFloat($('#gross_weight').val()) || 0;
                const net = parseFloat($('#net_weight').val()) || 0;
                const lost = Math.round((gross - net) * 1000) / 1000;

                $('#deduction-note').text(lost > 0 ? 'Deduction ' + lost.toFixed(3) + ' g' : '');
            }

            $('#repair_form').on('change', fillLines);
            $('#repair_form_line_id').on('change', fillFromLine);
            $('#metal_type_id').on('change', () => fillPurities(defaultPurityId));
            $('#gross_weight, #net_weight').on('input', refreshDeduction);

            fillPurities(oldPurityId || defaultPurityId);

            if ($('#repair_form').val()) {
                fillLines();
            }
        });
    </script>
@endpush

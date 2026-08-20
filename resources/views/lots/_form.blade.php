@csrf

@php($lineRows = old('lines') ?? $lines->map(fn ($l) => [
    'item_group_id' => $l->item_group_id,
    'pieces' => $l->pieces,
    'tags' => $l->tags,
])->all())

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Lot Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Lot Number</label>
                        <input type="text" class="form-control" value="{{ $lot->code ?? 'Auto on save' }}" disabled>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="lot_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" id="lot_date" name="lot_date"
                            class="form-control @error('lot_date') is-invalid @enderror"
                            value="{{ old('lot_date', optional($lot->lot_date)->toDateString() ?? today()->toDateString()) }}"
                            required>
                        @error('lot_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-select">
                            <option value="">None — in-house</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    @selected(old('supplier_id', $lot->supplier_id) == $supplier->id)>
                                    {{ $supplier->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <p class="text-muted fs-13 mb-2">
                            These carry into the entry screen as the batch defaults; they can still be changed there.
                        </p>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="metal_type_id" class="form-label">Metal Type</label>
                        <select id="metal_type_id" name="metal_type_id" class="form-select">
                            <option value="">Not set</option>
                            @foreach ($metalTypes as $metalType)
                                <option value="{{ $metalType->id }}"
                                    @selected(old('metal_type_id', $lot->metal_type_id) == $metalType->id)>
                                    {{ $metalType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="purity_id" class="form-label">Purity</label>
                        <select id="purity_id" name="purity_id" class="form-select">
                            <option value="">Not set</option>
                            @foreach ($purities as $purity)
                                <option value="{{ $purity->id }}" data-metal="{{ $purity->metal_type_id }}"
                                    @selected(old('purity_id', $lot->purity_id) == $purity->id)>
                                    {{ $purity->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="making_charge_id" class="form-label">Making Charge</label>
                        <select id="making_charge_id" name="making_charge_id" class="form-select">
                            <option value="">Not set</option>
                            @foreach ($makingCharges as $charge)
                                <option value="{{ $charge->id }}"
                                    @selected(old('making_charge_id', $lot->making_charge_id) == $charge->id)>
                                    {{ $charge->code }} — {{ $charge->summary() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="total_gross_weight" class="form-label">Declared Gross (g)</label>
                        <input type="number" step="0.001" min="0" id="total_gross_weight" name="total_gross_weight"
                            class="form-control @error('total_gross_weight') is-invalid @enderror"
                            value="{{ old('total_gross_weight', $lot->total_gross_weight) }}">
                        <small class="text-muted">Optional target, never a limit.</small>
                        @error('total_gross_weight')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="total_net_weight" class="form-label">Declared Net (g)</label>
                        <input type="number" step="0.001" min="0" id="total_net_weight" name="total_net_weight"
                            class="form-control @error('total_net_weight') is-invalid @enderror"
                            value="{{ old('total_net_weight', $lot->total_net_weight) }}">
                        @error('total_net_weight')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $lot->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-light-subtle border">
            <div class="card-header py-2">
                <h5 class="mb-0">Lot Totals</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td>Groups</td>
                            <td class="text-end"><span id="sum-lines">0</span></td>
                        </tr>
                        <tr>
                            <td>Total pieces</td>
                            <td class="text-end"><span id="sum-pieces">0</span></td>
                        </tr>
                        <tr class="table-active">
                            <th>Total tags <small class="text-muted">(items to create)</small></th>
                            <th class="text-end"><span id="sum-tags">0</span></th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @include('lots.partials.photo-card')
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <h5 class="mb-0">Groups Received</h5>
        <button type="button" class="btn btn-sm btn-primary" id="add-line">
            <i class="ri-add-line"></i> Add Group
        </button>
    </div>
    <div class="card-body">
        <p class="text-muted fs-13">
            <strong>Pieces</strong> is the physical count. <strong>Tags</strong> is how many item
            records to create — a pair of earrings is 2 pieces but 1 tag.
        </p>

        @error('lines')
            <div class="alert alert-danger py-2 fs-13">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table table-sm table-centered mb-0" id="lines-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 45%">Item Group</th>
                        <th style="width: 20%">Pieces</th>
                        <th style="width: 20%">Tags</th>
                        <th style="width: 15%"></th>
                    </tr>
                </thead>
                <tbody id="lines-body">
                    @foreach ($lineRows as $i => $line)
                        @include('lots.partials._line-row', ['index' => $i, 'line' => $line, 'groups' => $groups])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<template id="line-row-template">
    @include('lots.partials._line-row', ['index' => '__INDEX__', 'line' => null, 'groups' => $groups])
</template>

<div class="mb-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $lot->exists ? 'Update' : 'Create' }} Lot
    </button>
    <a href="{{ route('lots.index') }}" class="btn btn-light">Cancel</a>
</div>

@push('js')
    <script>
        $(function () {
            let nextIndex = {{ count($lineRows) }};

            function refreshTotals() {
                let pieces = 0, tags = 0, lines = 0;

                $('#lines-body tr').each(function () {
                    if (! $(this).find('.line-group').val()) {
                        return;
                    }
                    lines += 1;
                    pieces += parseInt($(this).find('.line-pieces').val(), 10) || 0;
                    tags += parseInt($(this).find('.line-tags').val(), 10) || 0;
                });

                $('#sum-lines').text(lines);
                $('#sum-pieces').text(pieces);
                $('#sum-tags').text(tags);
            }

            $('#add-line').on('click', function () {
                const html = $('#line-row-template').html().replace(/__INDEX__/g, nextIndex++);
                $('#lines-body').append(html);
                refreshTotals();
            });

            $(document).on('click', '.remove-line', function () {
                $(this).closest('tr').remove();
                refreshTotals();
            });

            $(document).on('input change', '#lines-body input, #lines-body select', refreshTotals);

            // Start a new lot with one blank line ready.
            if (nextIndex === 0) {
                $('#add-line').trigger('click');
            }

            refreshTotals();
        });
    </script>
@endpush

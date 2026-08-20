@csrf

<div class="card">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Lot No</label>
                {{-- Issued by the system under a lock, so it is never typed. --}}
                <input type="text" class="form-control bg-light" value="{{ $nextLotNo }}" readonly>
                @unless ($hallmark->exists)
                    <small class="text-muted">Assigned on save.</small>
                @endunless
            </div>

            <div class="col-md-3">
                <label for="hallmark_date" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="hallmark_date" name="hallmark_date"
                    class="form-control @error('hallmark_date') is-invalid @enderror"
                    value="{{ old('hallmark_date', optional($hallmark->hallmark_date)->toDateString() ?? today()->toDateString()) }}"
                    required>
                @error('hallmark_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 text-end align-self-end">
                <button type="button" class="btn btn-primary" id="hm-add-row">
                    <i class="ri-add-line"></i> Add Line
                </button>
            </div>
        </div>

        @error('lines')
            <div class="alert alert-danger py-2 fs-13">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table table-sm table-centered mb-0" id="hm-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%">ITEM</th>
                        <th style="width: 24%">DESCRIPTION</th>
                        <th style="width: 11%">PURITY</th>
                        <th style="width: 10%">QUANTITY</th>
                        <th style="width: 11%">PCS PER ITEM</th>
                        <th style="width: 12%">TOTAL QUANTITY</th>
                        <th style="width: 12%">SC</th>
                        <th style="width: 5%"></th>
                    </tr>
                </thead>
                <tbody id="hm-body">
                    @foreach (($lines ?? collect()) as $i => $line)
                        @include('hallmarks.partials._line-row', ['index' => $i, 'line' => $line])
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-muted fs-13 mt-2 mb-0">
            Total quantity is quantity &times; pcs per item. A line with no item or no
            quantity is ignored on save.
        </p>
    </div>
</div>

<template id="hm-row-template">
    @include('hallmarks.partials._line-row', ['index' => '__INDEX__', 'line' => null])
</template>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Total Pieces</label>
                        <input type="text" class="form-control bg-light" id="hm-total-pieces" value="0" readonly>
                    </div>

                    <div class="col-md-6">
                        <label for="cost_per_piece" class="form-label">Cost Per Piece <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" id="cost_per_piece" name="cost_per_piece"
                            class="form-control @error('cost_per_piece') is-invalid @enderror"
                            value="{{ old('cost_per_piece', $hallmark->cost_per_piece) }}" required>
                        @error('cost_per_piece')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="gross_weight" class="form-label">Gross Wt <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0" id="gross_weight" name="gross_weight"
                            class="form-control @error('gross_weight') is-invalid @enderror"
                            value="{{ old('gross_weight', $hallmark->gross_weight) }}" required>
                        @error('gross_weight')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Total Cost</label>
                        <input type="text" class="form-control bg-light" id="hm-total-cost" value="0" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Photo</h5>
            </div>
            <div class="card-body text-center">
                @if ($hallmark->photoUrl())
                    <img src="{{ $hallmark->photoUrl() }}" alt="Lot {{ $hallmark->lot_no }}"
                        class="img-fluid rounded mb-2" style="max-height: 150px;">
                @else
                    <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2"
                        style="height: 100px;">
                        <span class="text-muted"><i class="ri-image-line fs-24 d-block mb-1"></i>No photo</span>
                    </div>
                @endif

                <input type="file" name="photo" class="form-control form-control-sm"
                    accept="image/png,image/jpeg,image/webp">
                <small class="text-muted d-block mt-1">Optional. Prints on the docket.</small>
                @error('photo')
                    <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                @enderror

                @if ($hallmark->hasPhoto())
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

<div class="mb-4 d-flex gap-2">
    <a href="{{ route('hallmarks.index') }}" class="btn btn-light">Cancel</a>

    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $hallmark->exists ? 'Update' : 'Create' }}
    </button>

    @can('hallmark.print')
        @unless ($hallmark->exists)
            <button type="submit" name="print_after_save" value="1" class="btn btn-primary">
                <i class="ri-printer-line"></i> Create &amp; Print
            </button>
        @endunless
    @endcan
</div>

@push('js')
    <script>
        $(function () {
            let nextIndex = {{ ($lines ?? collect())->count() }};

            function refresh() {
                let pieces = 0;

                $('#hm-body tr.hm-row').each(function () {
                    const $row = $(this);
                    const quantity = parseInt($row.find('.hm-quantity').val(), 10) || 0;
                    const per = parseInt($row.find('.hm-pcs').val(), 10) || 0;
                    const total = quantity * per;

                    $row.find('.hm-total').val(total);
                    pieces += total;
                });

                const cost = parseFloat($('#cost_per_piece').val()) || 0;

                $('#hm-total-pieces').val(pieces);
                $('#hm-total-cost').val((pieces * cost).toFixed(2));
            }

            $('#hm-add-row').on('click', function () {
                const html = $('#hm-row-template').html().replace(/__INDEX__/g, nextIndex++);
                $('#hm-body').append(html);
                refresh();
            });

            $(document).on('click', '.hm-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            // Choosing an item fills the description, which then stays as typed.
            $(document).on('change', '.hm-group', function () {
                const $row = $(this).closest('tr');
                const name = $(this).find('option:selected').data('name') || '';

                $row.find('.hm-description').val(name);
            });

            $(document).on('input change', '#hm-body input, #hm-body select', refresh);
            $('#cost_per_piece').on('input', refresh);

            // A new docket opens with one blank line ready to fill.
            if (nextIndex === 0) {
                $('#hm-add-row').trigger('click');
            }

            refresh();
        });
    </script>
@endpush

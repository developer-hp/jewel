@csrf

@php($types = \App\Models\MakingCharge::TYPES)
@php($bases = \App\Models\MakingCharge::WEIGHT_BASES)

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" id="code" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror"
            value="{{ old('code', $charge->code) }}" placeholder="MC-PG350" maxlength="30" required>
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $charge->name) }}" placeholder="Per Gram — Standard" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="charge_type" class="form-label">Charge Type <span class="text-danger">*</span></label>
        <select id="charge_type" name="charge_type" class="form-select @error('charge_type') is-invalid @enderror" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('charge_type', $charge->charge_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('charge_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="rate" class="form-label">
            <span id="rate-label">Rate</span> <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text" id="rate-prefix">₹</span>
            <input type="number" step="0.0001" id="rate" name="rate"
                class="form-control @error('rate') is-invalid @enderror" value="{{ old('rate', $charge->rate) }}"
                min="0" required>
        </div>
        @error('rate')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3" id="weight-basis-wrapper">
        <label for="weight_basis" class="form-label">Weight Basis <span class="text-danger">*</span></label>
        <select id="weight_basis" name="weight_basis" class="form-select @error('weight_basis') is-invalid @enderror">
            @foreach ($bases as $value => $label)
                <option value="{{ $value }}" @selected(old('weight_basis', $charge->weight_basis) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <small class="text-muted">Which weight the per-gram rate multiplies.</small>
        @error('weight_basis')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mb-3">
        <div class="alert alert-light border mb-0">
            <i class="ri-information-line me-1"></i> This charge will read as:
            <strong id="charge-preview">{{ $charge->exists ? $charge->summary() : '—' }}</strong>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $charge->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $charge->exists ? 'Update' : 'Create' }} Making Charge
    </button>
    <a href="{{ route('making-charges.index') }}" class="btn btn-light">Cancel</a>
</div>

@push('js')
    <script>
        $(function () {
            const basisLabels = @js($bases);

            function refresh() {
                const type = $('#charge_type').val();
                const rate = parseFloat($('#rate').val() || 0);
                const isPercent = type === 'percentage';
                const isPerGram = type === 'per_gram';

                // Only per-gram charges need a weight basis.
                $('#weight-basis-wrapper').toggle(isPerGram);
                $('#rate-prefix').text(isPercent ? '%' : '₹');
                $('#rate-label').text(isPercent ? 'Percentage' : 'Rate');

                let preview = '—';
                if (type === 'fixed') {
                    preview = '₹' + rate.toFixed(2);
                } else if (isPerGram) {
                    preview = '₹' + rate.toFixed(2) + ' / g (' + ($('#weight_basis').val() || 'net') + ')';
                } else if (isPercent) {
                    preview = rate.toFixed(2) + '% of metal value';
                }
                $('#charge-preview').text(preview);
            }

            $('#charge_type, #rate, #weight_basis').on('input change', refresh);
            refresh();
        });
    </script>
@endpush

@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="purity_id" class="form-label">Purity <span class="text-danger">*</span></label>
        <select id="purity_id" name="purity_id" class="form-select @error('purity_id') is-invalid @enderror" required>
            <option value="">Select…</option>
            @foreach ($purities as $id => $label)
                <option value="{{ $id }}" @selected(old('purity_id', $rate->purity_id) == $id)>{{ $label }}</option>
            @endforeach
        </select>
        @error('purity_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="effective_date" class="form-label">Effective Date <span class="text-danger">*</span></label>
        <input type="date" id="effective_date" name="effective_date"
            class="form-control @error('effective_date') is-invalid @enderror"
            value="{{ old('effective_date', optional($rate->effective_date)->toDateString() ?? today()->toDateString()) }}"
            required>
        @error('effective_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="rate" class="form-label">Rate (₹) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" id="rate" name="rate" class="form-control @error('rate') is-invalid @enderror"
            value="{{ old('rate', $rate->rate) }}" min="0" required>
        @error('rate')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="per_grams" class="form-label">Per Grams <span class="text-danger">*</span></label>
        <input type="number" step="0.001" id="per_grams" name="per_grams"
            class="form-control @error('per_grams') is-invalid @enderror"
            value="{{ old('per_grams', $rate->per_grams ?? 10) }}" min="0.001" required>
        <small class="text-muted">10 for gold, 1000 for silver.</small>
        @error('per_grams')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Works Out To</label>
        <div class="form-control-plaintext">
            <span class="badge bg-primary-subtle text-primary fs-14" id="per-gram-preview">—</span>
            <span class="text-muted">/ gram</span>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $rate->exists ? 'Update' : 'Save' }} Rate
    </button>
    <a href="{{ route('rates.index') }}" class="btn btn-light">Cancel</a>
</div>

@push('js')
    <script>
        $(function () {
            function refresh() {
                const rate = parseFloat($('#rate').val());
                const per = parseFloat($('#per_grams').val());
                $('#per-gram-preview').text(rate > 0 && per > 0 ? '₹' + (rate / per).toFixed(4) : '—');
            }

            $('#rate, #per_grams').on('input', refresh);
            refresh();
        });
    </script>
@endpush

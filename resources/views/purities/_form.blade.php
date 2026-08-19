@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="metal_type_id" class="form-label">Metal Type <span class="text-danger">*</span></label>
        <select id="metal_type_id" name="metal_type_id"
            class="form-select @error('metal_type_id') is-invalid @enderror" required>
            <option value="">Select…</option>
            @foreach ($metalTypes as $id => $name)
                <option value="{{ $id }}" @selected(old('metal_type_id', $purity->metal_type_id) == $id)>{{ $name }}</option>
            @endforeach
        </select>
        @error('metal_type_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Purity Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $purity->name) }}" placeholder="22K, 916, 999" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="touch" class="form-label">Touch / Fineness %</label>
        <input type="number" step="0.001" id="touch" name="touch"
            class="form-control @error('touch') is-invalid @enderror" value="{{ old('touch', $purity->touch) }}"
            placeholder="91.600" min="0" max="100">
        @error('touch')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="default_per_grams" class="form-label">Rate Basis (grams) <span class="text-danger">*</span></label>
        <input type="number" step="0.001" id="default_per_grams" name="default_per_grams"
            class="form-control @error('default_per_grams') is-invalid @enderror"
            value="{{ old('default_per_grams', $purity->default_per_grams ?? 10) }}" min="0.001" required>
        <small class="text-muted">How many grams the daily rate is quoted against — 10 for gold, 1000 for silver.</small>
        @error('default_per_grams')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" class="form-control"
            value="{{ old('sort_order', $purity->sort_order ?? 0) }}" min="0">
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $purity->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $purity->exists ? 'Update' : 'Create' }} Purity
    </button>
    <a href="{{ route('purities.index') }}" class="btn btn-light">Cancel</a>
</div>

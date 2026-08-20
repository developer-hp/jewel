@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $stockGroup->name) }}" maxlength="100" placeholder="e.g. Plain Gold" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" id="code" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror"
            value="{{ old('code', $stockGroup->code) }}" maxlength="20" placeholder="PG" required>
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" class="form-control"
            value="{{ old('sort_order', $stockGroup->sort_order ?? 0) }}" min="0">
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $stockGroup->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $stockGroup->exists ? 'Update' : 'Create' }} Stock Group
    </button>
    <a href="{{ route('stock-groups.index') }}" class="btn btn-light">Cancel</a>
</div>

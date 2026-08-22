@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control text-uppercase @error('name') is-invalid @enderror"
            value="{{ old('name', $stock->name) }}" maxlength="100" placeholder="KARIGAR, FINE, OLD GOLD" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" class="form-control"
            value="{{ old('sort_order', $stock->sort_order ?? 0) }}" min="0">
        <small class="text-muted">Orders the balance cards.</small>
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="reset_on_opening" value="0">
            <input class="form-check-input" type="checkbox" id="reset_on_opening" name="reset_on_opening" value="1"
                @checked(old('reset_on_opening', $stock->reset_on_opening ?? true))>
            <label class="form-check-label" for="reset_on_opening">Reset stock on opening</label>
        </div>
        <small class="text-muted">Recorded for now; the opening routine that reads it comes later.</small>
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $stock->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $stock->exists ? 'Update' : 'Create' }} Internal Stock
    </button>
    <a href="{{ route('internal-stocks.index') }}" class="btn btn-light">Cancel</a>
</div>

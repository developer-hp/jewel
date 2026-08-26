@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $drawer->name) }}" maxlength="100" placeholder="Counter 1" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="opening_balance" class="form-label">Opening Balance</label>
        <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance"
            class="form-control @error('opening_balance') is-invalid @enderror"
            value="{{ old('opening_balance', $drawer->opening_balance) }}">
        <small class="text-muted">What the till started with. Set once, not daily.</small>
        @error('opening_balance')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" min="0" id="sort_order" name="sort_order"
            class="form-control @error('sort_order') is-invalid @enderror"
            value="{{ old('sort_order', $drawer->sort_order) }}">
        @error('sort_order')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $drawer->is_active))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $drawer->exists ? 'Update' : 'Create' }} Drawer
    </button>
    <a href="{{ route('cash-drawers.index') }}" class="btn btn-light">Cancel</a>
</div>

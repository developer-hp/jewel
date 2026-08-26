@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $receiver->name) }}" maxlength="150" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="mobile" class="form-label">Mobile <span class="text-danger">*</span></label>
        <input type="text" id="mobile" name="mobile" class="form-control @error('mobile') is-invalid @enderror"
            value="{{ old('mobile', $receiver->mobile) }}" maxlength="30" placeholder="9601263350" required>
        <small class="text-muted">Ten digits, or with the country code.</small>
        @error('mobile')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" min="0" id="sort_order" name="sort_order" class="form-control"
            value="{{ old('sort_order', $receiver->sort_order) }}">
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $receiver->is_active))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $receiver->exists ? 'Update' : 'Add' }} Receiver
    </button>
    <a href="{{ route('whatsapp-receivers.index') }}" class="btn btn-light">Cancel</a>
</div>

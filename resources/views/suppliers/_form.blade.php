@csrf

<p class="text-muted fs-13">Only the name is required — fill the rest in as you have it.</p>

<div class="row">
    <div class="col-md-8 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $supplier->name) }}" maxlength="150" required autofocus>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="short_name" class="form-label">Short Name</label>
        <input type="text" id="short_name" name="short_name"
            class="form-control @error('short_name') is-invalid @enderror"
            value="{{ old('short_name', $supplier->short_name) }}" maxlength="50" placeholder="Used in dropdowns">
        @error('short_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="city" class="form-label">City</label>
        <input type="text" id="city" name="city" class="form-control @error('city') is-invalid @enderror"
            value="{{ old('city', $supplier->city) }}" maxlength="100">
        @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone', $supplier->phone) }}" maxlength="30">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mb-3">
        <label for="address" class="form-label">Address</label>
        <textarea id="address" name="address" rows="2" maxlength="500"
            class="form-control @error('address') is-invalid @enderror">{{ old('address', $supplier->address) }}</textarea>
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $supplier->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active — inactive suppliers stay off the item form</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $supplier->exists ? 'Update' : 'Create' }} Supplier
    </button>
    <a href="{{ route('suppliers.index') }}" class="btn btn-light">Cancel</a>
</div>

@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Permission Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $permission->name) }}" placeholder="e.g. quotation.approve" required>
        <small class="text-muted">
            Lowercase <code>module.action</code>. The module prefix groups it on the role screen.
        </small>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $permission->exists ? 'Update Permission' : 'Create Permission' }}
    </button>
    <a href="{{ route('permissions.index') }}" class="btn btn-light">Cancel</a>
</div>

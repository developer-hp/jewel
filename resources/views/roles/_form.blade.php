@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Role Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $role->name) }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<hr>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Permissions</h5>
        <p class="text-muted fs-13 mb-0">Tick everything this role is allowed to do.</p>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="check-all-permissions">
        <label class="form-check-label fw-semibold" for="check-all-permissions">Select all</label>
    </div>
</div>

@php($checked = old('permissions', $selectedPermissions))

<div class="row">
    @foreach ($groupedPermissions as $module => $permissions)
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card border h-100 mb-0">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h5 class="mb-0 text-capitalize fs-14">{{ str_replace('_', ' ', $module) }}</h5>
                    <div class="form-check mb-0">
                        <input class="form-check-input module-check-all" type="checkbox"
                            data-module="{{ $module }}" id="module-{{ $module }}">
                        <label class="form-check-label fs-12" for="module-{{ $module }}">All</label>
                    </div>
                </div>
                <div class="card-body py-2">
                    @foreach ($permissions as $permission)
                        <div class="form-check mb-1">
                            <input class="form-check-input permission-check" type="checkbox" name="permissions[]"
                                value="{{ $permission->name }}" data-module="{{ $module }}"
                                id="perm-{{ $permission->id }}" @checked(in_array($permission->name, $checked, true))>
                            <label class="form-check-label" for="perm-{{ $permission->id }}">
                                {{ ucfirst(Str::after($permission->name, '.')) }}
                                <small class="text-muted">({{ $permission->name }})</small>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $role->exists ? 'Update Role' : 'Create Role' }}
    </button>
    <a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>
</div>

@push('js')
    <script>
        (function () {
            const syncModule = (module) => {
                const boxes = document.querySelectorAll('.permission-check[data-module="' + module + '"]');
                const toggle = document.querySelector('.module-check-all[data-module="' + module + '"]');
                toggle.checked = boxes.length > 0 && [...boxes].every(b => b.checked);
            };

            document.querySelectorAll('.module-check-all').forEach(toggle => {
                toggle.addEventListener('change', () => {
                    document.querySelectorAll('.permission-check[data-module="' + toggle.dataset.module + '"]')
                        .forEach(b => b.checked = toggle.checked);
                });
                syncModule(toggle.dataset.module);
            });

            document.querySelectorAll('.permission-check').forEach(box => {
                box.addEventListener('change', () => syncModule(box.dataset.module));
            });

            document.getElementById('check-all-permissions').addEventListener('change', function () {
                document.querySelectorAll('.permission-check, .module-check-all')
                    .forEach(b => b.checked = this.checked);
            });
        })();
    </script>
@endpush

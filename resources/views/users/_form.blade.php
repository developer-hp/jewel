@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $user->name) }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
        <input type="text" id="username" name="username" class="form-control @error('username') is-invalid @enderror"
            value="{{ old('username', $user->username) }}" required autocomplete="off">
        <small class="text-muted">Letters, numbers, dashes and underscores only. Used to log in.</small>
        @error('username')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
            value="{{ old('email', $user->email) }}">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone', $user->phone) }}">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="password" class="form-label">
            Password @if (! $user->exists)
                <span class="text-danger">*</span>
            @endif
        </label>
        <input type="password" id="password" name="password"
            class="form-control @error('password') is-invalid @enderror" autocomplete="new-password"
            @if (! $user->exists) required @endif>
        @if ($user->exists)
            <small class="text-muted">Leave blank to keep the current password.</small>
        @endif
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
            autocomplete="new-password" @if (! $user->exists) required @endif>
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $user->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active — an inactive user cannot log in</label>
        </div>
    </div>
</div>

<hr>

<h5 class="mb-2">Roles</h5>
<p class="text-muted fs-13">A user's permissions are the union of every role assigned to them.</p>

<div class="row">
    @forelse ($roles as $role)
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role }}"
                    id="role-{{ Str::slug($role) }}" @checked(in_array($role, old('roles', $selectedRoles), true))>
                <label class="form-check-label" for="role-{{ Str::slug($role) }}">{{ $role }}</label>
            </div>
        </div>
    @empty
        <div class="col-12">
            <p class="text-muted mb-0">No roles available.</p>
        </div>
    @endforelse
</div>

@error('roles')
    <div class="text-danger fs-13 mt-1">{{ $message }}</div>
@enderror

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $user->exists ? 'Update User' : 'Create User' }}
    </button>
    <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
</div>

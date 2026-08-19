@extends('layouts.app')

@section('title', 'Role Details')

@section('content')
    <x-page-title title="Role — {{ $role->name }}">
        <x-slot:actions>
            @can('role.edit')
                @if ($role->name !== 'Super Admin')
                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-primary">
                        <i class="ri-pencil-line"></i> Edit
                    </a>
                @endif
            @endcan
            <a href="{{ route('roles.index') }}" class="btn btn-light">Back</a>
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="header-title mb-3">Permissions</h5>

                    @if ($role->name === 'Super Admin')
                        <p class="text-muted mb-0">
                            The Super Admin role bypasses every permission check and always has full access.
                        </p>
                    @else
                        @forelse ($role->permissions->groupBy(fn ($p) => Str::before($p->name, '.')) as $module => $permissions)
                            <div class="mb-2">
                                <span class="fw-semibold text-capitalize me-2">{{ $module }}:</span>
                                @foreach ($permissions as $permission)
                                    <span class="badge bg-primary-subtle text-primary">{{ Str::after($permission->name, '.') }}</span>
                                @endforeach
                            </div>
                        @empty
                            <p class="text-muted mb-0">This role has no permissions yet.</p>
                        @endforelse
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="header-title mb-3">Users with this role ({{ $role->users->count() }})</h5>
                    @forelse ($role->users as $user)
                        <div class="d-flex align-items-center mb-2">
                            <i class="ri-user-line me-2"></i>
                            <div>
                                <div class="fw-semibold">{{ $user->name }}</div>
                                <small class="text-muted">{{ $user->username }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No users are assigned to this role.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

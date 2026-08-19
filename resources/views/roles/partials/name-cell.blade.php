<span class="fw-semibold">{{ $role->name }}</span>
@if ($role->name === 'Super Admin')
    <span class="badge bg-dark ms-1">locked</span>
@endif

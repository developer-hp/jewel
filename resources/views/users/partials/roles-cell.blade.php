@forelse ($user->roles as $role)
    <span class="badge bg-info-subtle text-info">{{ $role->name }}</span>
@empty
    <span class="text-muted">No role</span>
@endforelse

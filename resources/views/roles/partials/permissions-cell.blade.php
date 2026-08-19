@if ($role->name === 'Super Admin')
    <span class="badge bg-success">All permissions</span>
@else
    {{ $role->permissions_count }}
@endif

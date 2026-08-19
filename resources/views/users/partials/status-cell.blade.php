<span class="badge {{ $user->is_active ? 'bg-success' : 'bg-danger' }}">
    {{ $user->is_active ? 'Active' : 'Inactive' }}
</span>

<x-row-actions :edit-url="route('users.edit', $user)" :delete-url="route('users.destroy', $user)"
    edit-permission="user.edit" delete-permission="user.delete"
    :confirm="'Delete user ' . $user->username . '?'">
    <x-slot:before>
        @can('user.edit')
            <form action="{{ route('users.toggle-status', $user) }}" method="POST">
                @csrf
                <button type="submit"
                    class="btn btn-sm btn-icon {{ $user->is_active ? 'btn-soft-success' : 'btn-soft-secondary' }}"
                    title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                    <i class="{{ $user->is_active ? 'ri-toggle-fill' : 'ri-toggle-line' }}"></i>
                </button>
            </form>
        @endcan
    </x-slot:before>
</x-row-actions>

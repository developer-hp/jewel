@php($locked = $role->name === 'Super Admin')

<x-row-actions :edit-url="$locked ? null : route('roles.edit', $role)"
    :delete-url="$locked ? null : route('roles.destroy', $role)"
    edit-permission="role.edit" delete-permission="role.delete"
    :confirm="'Delete role ' . $role->name . '?'">
    <x-slot:before>
        @can('role.view')
            <a href="{{ route('roles.show', $role) }}" class="btn btn-sm btn-soft-secondary btn-icon" title="View">
                <i class="ri-eye-fill"></i>
            </a>
        @endcan
    </x-slot:before>
</x-row-actions>

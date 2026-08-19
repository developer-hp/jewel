@if ($isCore)
    <span class="badge bg-dark">core</span>
@else
    <x-row-actions :edit-url="route('permissions.edit', $permission)"
        :delete-url="route('permissions.destroy', $permission)"
        edit-permission="permission.edit" delete-permission="permission.delete"
        :confirm="'Delete ' . $permission->name . '? It will be removed from every role.'" />
@endif

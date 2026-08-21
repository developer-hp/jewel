{{-- A reserved group backs a module and cannot be deleted, so it shows no bin. --}}
<x-row-actions :edit-url="route('item-groups.edit', $group)"
    :delete-url="$group->isReserved() ? null : route('item-groups.destroy', $group)"
    edit-permission="item_group.edit" delete-permission="item_group.delete"
    :confirm="'Delete item group ' . $group->name . '?'" />

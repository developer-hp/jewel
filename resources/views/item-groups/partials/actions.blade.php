<x-row-actions :edit-url="route('item-groups.edit', $group)" :delete-url="route('item-groups.destroy', $group)"
    edit-permission="item_group.edit" delete-permission="item_group.delete"
    :confirm="'Delete item group ' . $group->name . '?'" />

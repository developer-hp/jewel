<x-row-actions :edit-url="route('stock-groups.edit', $stockGroup)" :delete-url="route('stock-groups.destroy', $stockGroup)"
    edit-permission="stock_group.edit" delete-permission="stock_group.delete"
    :confirm="'Delete stock group ' . $stockGroup->name . '?'" />

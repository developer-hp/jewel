<x-row-actions :edit-url="route('order-types.edit', $type)" :delete-url="route('order-types.destroy', $type)"
    edit-permission="order_type.edit" delete-permission="order_type.delete"
    :confirm="'Delete order type ' . $type->name . '?'" />

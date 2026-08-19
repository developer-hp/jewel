<x-row-actions :edit-url="route('suppliers.edit', $supplier)" :delete-url="route('suppliers.destroy', $supplier)"
    edit-permission="supplier.edit" delete-permission="supplier.delete"
    :confirm="'Delete supplier ' . $supplier->name . '?'" />

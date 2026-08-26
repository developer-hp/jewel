<x-row-actions :edit-url="route('cash-drawers.edit', $drawer)"
    :delete-url="route('cash-drawers.destroy', $drawer)"
    edit-permission="cash_drawer.edit" delete-permission="cash_drawer.delete"
    :confirm="'Delete the drawer ' . $drawer->name . '?'" />

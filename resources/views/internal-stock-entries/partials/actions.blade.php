<x-row-actions :edit-url="route('internal-stock-entries.edit', $entry)"
    :delete-url="route('internal-stock-entries.destroy', $entry)"
    edit-permission="internal_stock_entry.edit" delete-permission="internal_stock_entry.delete"
    :confirm="'Delete this ' . $entry->typeLabel() . ' entry? The balance will change.'" />

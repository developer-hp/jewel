<x-row-actions :edit-url="route('cash-entries.edit', $entry)"
    :delete-url="route('cash-entries.destroy', $entry)"
    edit-permission="cash_entry.edit" delete-permission="cash_entry.delete"
    :confirm="'Delete cash entry ' . $entry->reference() . '?'" />

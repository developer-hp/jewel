<x-row-actions :edit-url="route('making-charges.edit', $charge)" :delete-url="route('making-charges.destroy', $charge)"
    edit-permission="making_charge.edit" delete-permission="making_charge.delete"
    :confirm="'Delete making charge ' . $charge->code . '?'" />

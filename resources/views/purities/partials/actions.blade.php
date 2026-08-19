<x-row-actions :edit-url="route('purities.edit', $purity)" :delete-url="route('purities.destroy', $purity)"
    edit-permission="purity.edit" delete-permission="purity.delete"
    :confirm="'Delete purity ' . $purity->label() . '?'" />

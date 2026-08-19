<x-row-actions :edit-url="route('metal-types.edit', $metalType)" :delete-url="route('metal-types.destroy', $metalType)"
    edit-permission="metal_type.edit" delete-permission="metal_type.delete"
    :confirm="'Delete metal type ' . $metalType->name . '?'" />

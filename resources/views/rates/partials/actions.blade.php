<x-row-actions :edit-url="route('rates.edit', $rate)" :delete-url="route('rates.destroy', $rate)"
    edit-permission="metal_rate.edit" delete-permission="metal_rate.delete"
    :confirm="'Delete the ' . $rate->effective_date->format('d M Y') . ' rate for ' . $rate->purity?->label() . '?'" />

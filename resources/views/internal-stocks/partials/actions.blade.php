<x-row-actions :edit-url="route('internal-stocks.edit', $stock)" :delete-url="route('internal-stocks.destroy', $stock)"
    edit-permission="internal_stock.edit" delete-permission="internal_stock.delete"
    :confirm="'Delete internal stock ' . $stock->name . '?'" />

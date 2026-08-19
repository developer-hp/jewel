<x-row-actions :edit-url="route($routePrefix . '.edit', $stone)" :delete-url="route($routePrefix . '.destroy', $stone)"
    edit-permission="stone.edit" delete-permission="stone.delete"
    :confirm="'Delete ' . strtolower($singular) . ' ' . $stone->name . '?'" />

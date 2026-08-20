<x-row-actions :edit-url="route('items.edit', $item)" :delete-url="route('items.destroy', $item)"
    edit-permission="item.edit" delete-permission="item.delete"
    :confirm="'Delete item ' . $item->code . '?'">
    <x-slot:before>
        @can('item.view')
            <a href="{{ route('items.show', $item) }}" class="btn btn-sm btn-soft-secondary btn-icon" title="View">
                <i class="ri-eye-fill"></i>
            </a>
        @endcan

        @can('item.print')
            <a href="{{ route('items.label', $item) }}" target="_blank"
                class="btn btn-sm btn-info btn-icon" title="Print tag">
                <i class="ri-price-tag-3-fill"></i>
            </a>
        @endcan
    </x-slot:before>
</x-row-actions>

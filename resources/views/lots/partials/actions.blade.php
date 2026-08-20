<x-row-actions :edit-url="route('lots.edit', $lot)" :delete-url="route('lots.destroy', $lot)"
    edit-permission="item_lot.edit" delete-permission="item_lot.delete"
    :confirm="'Delete lot ' . $lot->code . '?'">
    <x-slot:before>
        @can('item_lot.view')
            <a href="{{ route('lots.show', $lot) }}" class="btn btn-sm btn-soft-secondary btn-icon" title="View">
                <i class="ri-eye-fill"></i>
            </a>
        @endcan

        @can('item.create')
            @if ($lot->remainingTags() > 0)
                <a href="{{ route('lots.items.create', $lot) }}" class="btn btn-sm btn-success btn-icon"
                    title="Add items">
                    <i class="ri-add-box-fill"></i>
                </a>
            @endif
        @endcan
    </x-slot:before>
</x-row-actions>

<x-row-actions :edit-url="route('hallmarks.edit', $hallmark)" :delete-url="route('hallmarks.destroy', $hallmark)"
    edit-permission="hallmark.edit" delete-permission="hallmark.delete"
    :confirm="'Delete hallmark lot ' . $hallmark->lot_no . '?'">
    <x-slot:before>
        @can('hallmark.print')
            <a href="{{ route('hallmarks.print', $hallmark) }}" target="_blank"
                class="btn btn-sm btn-soft-info btn-icon" title="Print docket">
                <i class="ri-printer-fill"></i>
            </a>
        @endcan
    </x-slot:before>
</x-row-actions>

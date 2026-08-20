<x-row-actions :edit-url="route('angadiyas.edit', $angadiya)" :delete-url="route('angadiyas.destroy', $angadiya)"
    edit-permission="angadiya.edit" delete-permission="angadiya.delete"
    :confirm="'Delete the slip for ' . $angadiya->name . '?'">
    <x-slot:before>
        @can('angadiya.print')
            <button type="button" class="btn btn-sm btn-soft-info btn-icon print-one" data-id="{{ $angadiya->id }}"
                title="Print this slip">
                <i class="ri-printer-fill"></i>
            </button>
        @endcan
    </x-slot:before>
</x-row-actions>

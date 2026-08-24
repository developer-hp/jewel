{{--
    The default row has no delete button — passing null hides it. The controller
    refuses the delete as well; this only spares the user the dialog.
--}}
<x-row-actions :edit-url="route('label-settings.edit', $setting)"
    :delete-url="$setting->is_default ? null : route('label-settings.destroy', $setting)"
    edit-permission="label_setting.edit" delete-permission="label_setting.delete"
    :confirm="'Delete the template ' . $setting->name . '?'">
    <x-slot:before>
        @can('label_setting.edit')
            @unless ($setting->is_default)
                <form action="{{ route('label-settings.default', $setting) }}" method="POST"
                    data-confirm="Make &quot;{{ $setting->name }}&quot; the default template?">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success btn-icon" title="Make this the default">
                        <i class="ri-star-fill"></i>
                    </button>
                </form>
            @endunless
        @endcan

        @can('label_setting.create')
            <form action="{{ route('label-settings.duplicate', $setting) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning btn-icon" title="Copy to a new template">
                    <i class="ri-file-copy-fill"></i>
                </button>
            </form>
        @endcan
    </x-slot:before>
</x-row-actions>

<div class="row-actions">
    @can('item_estimate.print')
        <button type="button" class="btn btn-sm btn-secondary btn-icon print-one" data-id="{{ $estimate->id }}"
            title="Print this estimate">
            <i class="ri-printer-fill"></i>
        </button>
    @endcan

    @can('item_estimate.edit')
        <a href="{{ route('item-estimates.edit', $estimate) }}" class="btn btn-sm btn-primary btn-icon" title="Edit">
            <i class="ri-pencil-fill"></i>
        </a>
    @endcan

    @can('item_estimate.delete')
        <button type="button" class="btn btn-sm btn-danger btn-icon" title="Delete"
            data-delete-url="{{ route('item-estimates.destroy', $estimate) }}" data-delete-confirm="{{ 'Delete estimate '.$estimate->reference().'?' }}">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    @endcan
</div>

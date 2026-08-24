{{--
    Edit opens the modal on the listing rather than a page of its own, so it carries
    the row's values as data attributes instead of a URL.
--}}
<div class="row-actions">
    @can('supplier_hisab.print')
        <button type="button" class="btn btn-sm btn-info btn-icon print-one" data-id="{{ $hisab->id }}"
            title="Print this slip">
            <i class="ri-printer-fill"></i>
        </button>
    @endcan

    <a href="{{ route('supplier-hisabs.settle', $hisab) }}"
        class="btn btn-sm {{ $hisab->isSettled() ? 'btn-success' : 'btn-dark' }} btn-icon"
        title="{{ $hisab->isSettled() ? 'Settled — open to change' : 'Settle this hisab' }}">
        <i class="ri-calculator-fill"></i>
    </a>

    @can('supplier_hisab.edit')
        <button type="button" class="btn btn-sm btn-primary btn-icon hisab-edit" title="Edit"
            data-id="{{ $hisab->id }}" data-supplier="{{ $hisab->supplier_id }}"
            data-fine="{{ (float) $hisab->fine_baki }}" data-cash="{{ (float) $hisab->cash_baki }}">
            <i class="ri-pencil-fill"></i>
        </button>
    @endcan

    @can('supplier_hisab.delete')
        <button type="button" class="btn btn-sm btn-danger btn-icon" title="Delete"
            data-delete-url="{{ route('supplier-hisabs.destroy', $hisab) }}" data-delete-confirm="{{ 'Delete the hisab for '.$hisab->supplier_label.'?' }}">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    @endcan
</div>

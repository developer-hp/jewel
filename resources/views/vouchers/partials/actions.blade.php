<div class="row-actions">
    @can('voucher.print')
        <button type="button" class="btn btn-sm btn-secondary btn-icon print-one" data-id="{{ $voucher->id }}"
            title="Print this voucher">
            <i class="ri-printer-fill"></i>
        </button>
    @endcan

    @can('voucher.create')
        {{-- Copies onto a fresh ref; the original keeps its number. --}}
        <form action="{{ route('vouchers.copy', $voucher) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-warning btn-icon" title="Copy to a new voucher">
                <i class="ri-file-copy-fill"></i>
            </button>
        </form>
    @endcan

    @can('voucher.edit')
        <a href="{{ route('vouchers.edit', $voucher) }}" class="btn btn-sm btn-primary btn-icon" title="Edit">
            <i class="ri-pencil-fill"></i>
        </a>
    @endcan

    @can('voucher.delete')
        <button type="button" class="btn btn-sm btn-danger btn-icon" title="Delete"
            data-delete-url="{{ route('vouchers.destroy', $voucher) }}" data-delete-confirm="{{ 'Delete voucher '.$voucher->reference().'?' }}">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    @endcan
</div>

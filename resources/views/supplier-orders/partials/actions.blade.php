<div class="row-actions">
    @can('supplier_order.print')
        <button type="button" class="btn btn-sm btn-secondary btn-icon print-one" data-id="{{ $order->id }}"
            title="Print the karigar receipt">
            <i class="ri-printer-fill"></i>
        </button>
    @endcan

    @can('supplier_order.edit')
        @unless ($order->isReceived())
            <form action="{{ route('supplier-orders.received', $order) }}" method="POST"
                onsubmit="return confirm(@js('Mark order '.$order->form_no.' as received?'));">
                @csrf
                <button type="submit" class="btn btn-sm btn-success btn-icon" title="The goods came back">
                    <i class="ri-check-line"></i>
                </button>
            </form>
        @endunless

        <a href="{{ route('supplier-orders.edit', $order) }}" class="btn btn-sm btn-primary btn-icon" title="Edit">
            <i class="ri-pencil-fill"></i>
        </a>
    @endcan

    @can('supplier_order.delete')
        <button type="button" class="btn btn-sm btn-danger btn-icon" title="Delete"
            data-delete-url="{{ route('supplier-orders.destroy', $order) }}" data-delete-confirm="{{ 'Delete order '.$order->form_no.'?' }}">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    @endcan
</div>

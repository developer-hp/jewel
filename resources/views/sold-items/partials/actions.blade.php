{{--
    Both directions, always visible, so a mistake is one click to undo. Posts and
    reloads rather than going through the ajax delete handler — this is a state
    change, not a removal.
--}}
@can('item.edit')
    <div class="row-actions">
        @if ($item->isSold())
            <form action="{{ route('sold-items.available', $item) }}" method="POST"
                data-confirm="Put {{ $item->code }} back into stock?">
                @csrf
                <button type="submit" class="btn btn-sm btn-success btn-icon" title="Mark available">
                    <i class="ri-arrow-go-back-line"></i>
                </button>
            </form>
        @else
            <form action="{{ route('sold-items.sold', $item) }}" method="POST"
                data-confirm="Mark {{ $item->code }} sold today?">
                @csrf
                <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Mark sold">
                    <i class="ri-check-double-line"></i>
                </button>
            </form>
        @endif
    </div>
@endcan

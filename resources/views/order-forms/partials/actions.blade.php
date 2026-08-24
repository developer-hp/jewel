@php($ready = $form->lines_count > 0 && $form->ready_lines_count === $form->lines_count)

<div class="row-actions">
    @can('order_form.print')
        <button type="button" class="btn btn-sm btn-secondary btn-icon print-one" data-id="{{ $form->id }}"
            title="Print this order">
            <i class="ri-printer-fill"></i>
        </button>
        <button type="button" class="btn btn-sm btn-info btn-icon sticker-one" data-id="{{ $form->id }}"
            title="Print the bag sticker">
            <i class="ri-price-tag-3-fill"></i>
        </button>
    @endcan

    @can('order_form.edit')
        @unless ($ready)
            {{-- Making the piece is what holds it against the order, so the shortcut
                 only shows while something is still to be made. --}}
            <a href="{{ route('order-items.create', ['form' => $form->id]) }}"
                class="btn btn-sm btn-success btn-icon" title="Make a piece for this order">
                <i class="ri-hammer-fill"></i>
            </a>
        @endunless

        <a href="{{ route('order-forms.edit', $form) }}" class="btn btn-sm btn-primary btn-icon" title="Edit">
            <i class="ri-pencil-fill"></i>
        </a>
    @endcan

    @can('order_form.delete')
        <button type="button" class="btn btn-sm btn-danger btn-icon" title="Delete"
            data-delete-url="{{ route('order-forms.destroy', $form) }}" data-delete-confirm="{{ 'Delete order '.$form->reference().'?' }}">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    @endcan
</div>

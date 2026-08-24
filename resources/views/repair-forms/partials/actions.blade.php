@php($ready = $form->lines_count > 0 && $form->ready_lines_count === $form->lines_count)

<div class="row-actions">
    @can('repair_form.print')
        <button type="button" class="btn btn-sm btn-secondary btn-icon print-one" data-id="{{ $form->id }}"
            title="Print this form">
            <i class="ri-printer-fill"></i>
        </button>
        <button type="button" class="btn btn-sm btn-info btn-icon sticker-one" data-id="{{ $form->id }}"
            title="Print the bag sticker">
            <i class="ri-price-tag-3-fill"></i>
        </button>
    @endcan

    @can('repair_form.edit')
        @unless ($ready)
            {{-- Booking a returned piece is what marks a line done, so the shortcut
                 only shows while something is still out. --}}
            <a href="{{ route('repair-items.create', ['form' => $form->id]) }}"
                class="btn btn-sm btn-success btn-icon" title="Book a returned piece into stock">
                <i class="ri-hammer-fill"></i>
            </a>
        @endunless

        <a href="{{ route('repair-forms.edit', $form) }}" class="btn btn-sm btn-primary btn-icon" title="Edit">
            <i class="ri-pencil-fill"></i>
        </a>
    @endcan

    @can('repair_form.delete')
        <button type="button" class="btn btn-sm btn-danger btn-icon" title="Delete"
            data-delete-url="{{ route('repair-forms.destroy', $form) }}" data-delete-confirm="{{ 'Delete repair '.$form->reference().'?' }}">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    @endcan
</div>

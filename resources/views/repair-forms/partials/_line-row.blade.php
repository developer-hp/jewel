@php($booked = $line?->item)

{{--
    A line whose piece is already back in stock is pointed at by that item, so it
    carries no remove button — dropping it would break the link and un-ready the
    form. Its id posts either way, so the server updates it in place.
--}}
<tr class="repair-row">
    <td>
        @if ($line?->id)
            <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
        @endif
        <input type="text" name="lines[{{ $index }}][description]" class="form-control"
            value="{{ $line?->description }}" maxlength="255" placeholder="Description">
    </td>
    <td>
        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][net_weight]"
            class="form-control" value="{{ $line?->net_weight !== null ? (float) $line->net_weight : '' }}"
            placeholder="Net Weight">
    </td>
    <td class="text-center">
        @if ($booked)
            <span class="badge bg-success-subtle text-success" title="Back in stock">{{ $booked->code }}</span>
        @else
            <span class="text-muted fs-13">out</span>
        @endif
    </td>
    <td class="text-center">
        @unless ($booked)
            <button type="button" class="btn btn-sm btn-danger btn-icon repair-remove" title="Remove row">
                <i class="ri-close-line"></i>
            </button>
        @endunless
    </td>
</tr>

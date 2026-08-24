{{--
    One line of the estimate grid.

    Shared on purpose: the item-based estimate to come reuses this row and the
    arithmetic behind it, and only brings its own way of naming a line.
--}}
<tr class="estimate-row">
    <td>
        <input type="text" name="lines[{{ $index }}][description]" class="form-control"
            value="{{ $line?->description }}" maxlength="255" placeholder="Description">
    </td>
    <td>
        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][gross_weight]"
            class="form-control est-gross" value="{{ $line ? (float) $line->gross_weight : '' }}">
    </td>
    <td>
        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][net_weight]"
            class="form-control est-net" value="{{ $line ? (float) $line->net_weight : '' }}">
    </td>
    <td>
        <input type="number" step="0.001" min="0" max="100" name="lines[{{ $index }}][touch_percent]"
            class="form-control est-touch" value="{{ $line ? (float) $line->touch_percent : '' }}">
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="lines[{{ $index }}][rate]"
            class="form-control est-rate" value="{{ $line ? (float) $line->rate : '' }}"
            title="Per ten grams">
    </td>
    <td>
        {{-- Derived; the server recomputes it from EstimateLineMath on save. --}}
        <input type="text" class="form-control bg-light est-total" readonly tabindex="-1"
            value="{{ $line ? number_format($line->value(), 2) : '' }}">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-danger btn-icon est-remove" title="Remove row">
            <i class="ri-close-line"></i>
        </button>
    </td>
</tr>

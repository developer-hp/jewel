{{-- One row of scrap handed over. Fine Weight is display only — the server derives it. --}}
<tr class="hisab-row">
    <td>
        <input type="text" name="rows[{{ $index }}][item_name]" class="form-control"
            value="{{ $row?->item_name }}" maxlength="100" placeholder="Item">
    </td>
    <td>
        <input type="number" step="0.001" min="0" name="rows[{{ $index }}][gross_weight]"
            class="form-control hisab-gross" value="{{ $row ? (float) $row->gross_weight : '' }}">
    </td>
    <td>
        <input type="number" step="0.001" min="0" max="100" name="rows[{{ $index }}][touch]"
            class="form-control hisab-touch" value="{{ $row ? (float) $row->touch : 100 }}">
    </td>
    <td>
        <input type="text" class="form-control bg-light hisab-fine" value="{{ $row ? $row->fineWeight() : '' }}"
            readonly tabindex="-1">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-danger btn-icon hisab-remove" title="Remove row">
            <i class="ri-close-line"></i>
        </button>
    </td>
</tr>

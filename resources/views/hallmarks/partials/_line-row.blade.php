{{--
    One docket line.

    $index — array index, or __INDEX__ in the template
    $line  — an existing HallmarkLine, or null for a blank row

    The SC select lists suppliers by short_name (V-1 … V-200). With up to 200 of them
    the options are emitted once into the row <template> and cloned per row.
--}}
@php($line = $line ?? null)

<tr class="hm-row">
    <td>
        <select name="lines[{{ $index }}][item_group_id]" class="form-select form-select-sm hm-group">
            <option value="">Select…</option>
            @foreach ($groups as $group)
                <option value="{{ $group->id }}" data-name="{{ $group->name }}"
                    @selected($line && $line->item_group_id == $group->id)>
                    {{ $group->prefix }} — {{ $group->name }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" class="form-control form-control-sm hm-description"
            name="lines[{{ $index }}][description]" value="{{ $line->description ?? '' }}" maxlength="150">
    </td>
    <td>
        <select name="lines[{{ $index }}][purity_id]" class="form-select form-select-sm">
            <option value="">Select…</option>
            @foreach ($purities as $purity)
                <option value="{{ $purity->id }}" @selected($line && $line->purity_id == $purity->id)>
                    {{ $purity->name }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="number" min="1" step="1" class="form-control form-control-sm hm-quantity"
            name="lines[{{ $index }}][quantity]" value="{{ $line->quantity ?? '' }}">
    </td>
    <td>
        <input type="number" min="1" step="1" class="form-control form-control-sm hm-pcs"
            name="lines[{{ $index }}][pcs_per_quantity]" value="{{ $line->pcs_per_quantity ?? 1 }}">
    </td>
    <td>
        {{-- Derived: quantity x pcs per quantity. Recomputed server-side on save. --}}
        <input type="text" class="form-control form-control-sm bg-light hm-total" value="0" readonly tabindex="-1">
    </td>
    <td>
        <select name="lines[{{ $index }}][supplier_id]" class="form-select form-select-sm">
            <option value="">—</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected($line && $line->supplier_id == $supplier->id)>
                    {{ $supplier->short_name ?: $supplier->name }}
                </option>
            @endforeach
        </select>
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-danger btn-icon hm-remove" title="Remove">
            <i class="ri-close-line"></i>
        </button>
    </td>
</tr>

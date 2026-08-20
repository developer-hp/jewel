{{--
    One group line on a lot: which group, how many pieces arrived, how many tags
    (item records) to create for them.

    $index  — array index, or __INDEX__ in the template
    $line   — ['item_group_id','pieces','tags'] or null for a blank row
--}}
@php($line = $line ?? null)

<tr>
    <td>
        <select name="lines[{{ $index }}][item_group_id]" class="form-select form-select-sm line-group">
            <option value="">Select group…</option>
            @foreach ($groups as $group)
                <option value="{{ $group->id }}"
                    @selected($line && ($line['item_group_id'] ?? null) == $group->id)>
                    {{ $group->name }} ({{ $group->prefix }})
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="number" min="0" step="1" class="form-control form-control-sm line-pieces"
            name="lines[{{ $index }}][pieces]" value="{{ $line['pieces'] ?? 0 }}">
    </td>
    <td>
        <input type="number" min="1" step="1" class="form-control form-control-sm line-tags"
            name="lines[{{ $index }}][tags]" value="{{ $line['tags'] ?? 1 }}">
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-danger btn-icon remove-line" title="Remove">
            <i class="ri-delete-bin-2-fill"></i>
        </button>
    </td>
</tr>

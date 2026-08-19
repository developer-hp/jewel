{{--
    One stone/diamond line. Rendered server-side for existing rows and cloned by JS
    for new ones, so both paths always produce identical markup.

    $section  — 'stone' | 'diamond' (only affects which master list is offered)
    $masters  — StoneMaster collection for that kind
    $index    — array index, or __INDEX__ in the template
    $row      — existing ItemStone, or null for a blank line
--}}
@php($row = $row ?? null)

<tr class="stone-row">
    <td>
        <select name="stones[{{ $index }}][stone_master_id]" class="form-select form-select-sm stone-master" required>
            <option value="">Select {{ $section }}…</option>
            @foreach ($masters as $master)
                <option value="{{ $master->id }}" data-unit="{{ $master->rate_unit }}"
                    data-rate="{{ $master->default_rate }}"
                    @selected($row && $row->stone_master_id == $master->id)>
                    {{ $master->name }}@if ($master->code) ({{ $master->code }})@endif
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="number" min="0" step="1" class="form-control form-control-sm stone-pieces"
            name="stones[{{ $index }}][pieces]" value="{{ $row->pieces ?? 0 }}">
    </td>
    {{-- Carat and gram are two views of the same weight; typing in either fills the
         other. Carat is what gets stored, so a gram-only entry is converted back. --}}
    <td>
        <input type="number" min="0" step="0.001" class="form-control form-control-sm stone-carat"
            name="stones[{{ $index }}][weight_carat]" value="{{ $row->weight_carat ?? '0.000' }}">
    </td>
    <td>
        <input type="number" min="0" step="0.0001" class="form-control form-control-sm stone-grams"
            name="stones[{{ $index }}][weight_grams]" value="{{ $row->weight_grams ?? '0.0000' }}">
    </td>
    <td>
        <span class="stone-unit badge bg-secondary-subtle text-secondary">—</span>
    </td>
    <td>
        <input type="number" min="0" step="0.01" class="form-control form-control-sm stone-rate"
            name="stones[{{ $index }}][rate]" value="{{ $row->rate ?? '' }}" placeholder="master rate">
    </td>
    <td class="text-end">
        <span class="stone-amount fw-semibold">0.00</span>
    </td>
    <td class="text-center">
        <input type="hidden" name="stones[{{ $index }}][deduct_from_gross]" value="0">
        <input type="checkbox" class="form-check-input stone-deduct" name="stones[{{ $index }}][deduct_from_gross]"
            value="1" @checked($row?->deduct_from_gross ?? true)>
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row" title="Remove">
            <i class="ri-delete-bin-2-fill fs-18"></i>
        </button>
    </td>
</tr>

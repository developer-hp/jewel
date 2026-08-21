{{-- One stone or diamond on an order line. Carat is the source of truth; grams are
     derived by ItemCalculator on save, the same as on an item. --}}
<tr class="order-stone">
    <td style="width: 30%">
        <select name="lines[{{ $index }}][stones][{{ $sIndex }}][stone_master_id]"
            class="form-select form-select-sm order-stone-master" @disabled($ready ?? false)>
            <option value="">— remove —</option>
            <optgroup label="Stones">
                @foreach ($stoneMasters as $master)
                    <option value="{{ $master->id }}" data-unit="{{ $master->rate_unit }}"
                        data-rate="{{ (float) $master->default_rate }}"
                        @selected($stone?->stone_master_id == $master->id)>{{ $master->name }}</option>
                @endforeach
            </optgroup>
            <optgroup label="Diamonds">
                @foreach ($diamondMasters as $master)
                    <option value="{{ $master->id }}" data-unit="{{ $master->rate_unit }}"
                        data-rate="{{ (float) $master->default_rate }}"
                        @selected($stone?->stone_master_id == $master->id)>{{ $master->name }}</option>
                @endforeach
            </optgroup>
        </select>
    </td>
    <td style="width: 14%">
        <input type="number" min="0" name="lines[{{ $index }}][stones][{{ $sIndex }}][pieces]"
            class="form-control form-control-sm order-stone-pieces" value="{{ $stone?->pieces }}" placeholder="Pcs"
            @disabled($ready ?? false)>
    </td>
    <td style="width: 16%">
        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][stones][{{ $sIndex }}][weight_carat]"
            class="form-control form-control-sm order-stone-carat" value="{{ $stone ? (float) $stone->weight_carat : '' }}"
            placeholder="Carat" @disabled($ready ?? false)>
    </td>
    <td style="width: 16%">
        <input type="number" step="0.01" min="0" name="lines[{{ $index }}][stones][{{ $sIndex }}][rate]"
            class="form-control form-control-sm order-stone-rate" value="{{ $stone ? (float) $stone->rate : '' }}"
            placeholder="Rate" @disabled($ready ?? false)>
    </td>
    <td style="width: 16%">
        <div class="form-check">
            <input type="hidden" name="lines[{{ $index }}][stones][{{ $sIndex }}][deduct_from_gross]" value="0">
            <input class="form-check-input" type="checkbox" value="1"
                name="lines[{{ $index }}][stones][{{ $sIndex }}][deduct_from_gross]"
                @checked($stone?->deduct_from_gross ?? true) @disabled($ready ?? false)>
            <label class="form-check-label fs-13">Deduct</label>
        </div>
    </td>
    <td class="text-center" style="width: 8%">
        @unless ($ready ?? false)
            <button type="button" class="btn btn-sm btn-danger btn-icon order-remove-stone" title="Remove">
                <i class="ri-close-line"></i>
            </button>
        @endunless
    </td>
</tr>

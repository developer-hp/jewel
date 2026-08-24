{{--
    One stone or diamond inside a line's popup.

    Amount follows the master's rate unit — pieces, grams, carat or a flat figure —
    which is the same rule ItemCalculator applies on save.
--}}
<div class="card mb-2 est-stone">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label fs-12 mb-1">Style</label>
                <select name="lines[{{ $index }}][stones][{{ $sIndex }}][stone_master_id]"
                    class="form-select est-stone-master">
                    <option value="">— remove —</option>
                    @foreach ($stoneMasters as $master)
                        <option value="{{ $master->id }}" data-unit="{{ $master->rate_unit }}"
                            data-rate="{{ (float) $master->default_rate }}"
                            @selected($stone?->stone_master_id == $master->id)>{{ $master->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label fs-12 mb-1">NW</label>
                <input type="number" step="0.001" min="0" inputmode="decimal"
                    name="lines[{{ $index }}][stones][{{ $sIndex }}][weight_grams]"
                    class="form-control est-stone-grams"
                    value="{{ $stone && (float) $stone->weight_grams > 0 ? (float) $stone->weight_grams : '' }}">
            </div>

            <div class="col-4 col-md-2">
                <label class="form-label fs-12 mb-1">PCS</label>
                <input type="number" min="0" inputmode="numeric"
                    name="lines[{{ $index }}][stones][{{ $sIndex }}][pieces]"
                    class="form-control est-stone-pieces" value="{{ $stone && $stone->pieces ? $stone->pieces : '' }}">
            </div>

            <div class="col-4 col-md-2">
                <label class="form-label fs-12 mb-1">RATE</label>
                <input type="number" step="0.01" min="0" inputmode="decimal"
                    name="lines[{{ $index }}][stones][{{ $sIndex }}][rate]"
                    class="form-control est-stone-rate" value="{{ $stone ? (float) $stone->rate : '' }}">
            </div>

            <div class="col-3 col-md-1">
                <label class="form-label fs-12 mb-1">Amount</label>
                <input type="text" class="form-control bg-light est-stone-amount" readonly tabindex="-1"
                    value="{{ $stone ? number_format((float) $stone->amount, 0, '.', '') : '' }}">
            </div>

            <div class="col-1">
                <button type="button" class="btn btn-danger btn-icon est-stone-remove" title="Remove">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        </div>
    </div>
</div>

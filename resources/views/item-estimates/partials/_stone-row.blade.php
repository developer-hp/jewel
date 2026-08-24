{{--
    One stone or diamond inside a line's popup.

    Amount follows the master's rate unit — pieces, grams, carat or a flat figure —
    which is the same rule ItemCalculator applies on save.

    Ded. carries the item's own deduct_from_gross across, rather than assuming every
    stone comes out of the gross: a stone that was not deducted on the piece must not
    be deducted on the quote for it either.
--}}
<div class="card mb-2 est-stone">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
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

            {{-- Carat and gram are two views of one weight; typing in either fills
                 the other. Only grams is named, so the posted shape is unchanged and
                 the server still derives the carat from it. --}}
            <div class="col-6 col-md-2">
                <label class="form-label fs-12 mb-1">CT</label>
                <input type="number" step="0.001" min="0" inputmode="decimal"
                    class="form-control est-stone-carat"
                    value="{{ $stone && (float) $stone->weight_carat > 0 ? (float) $stone->weight_carat : '' }}">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label fs-12 mb-1">NW (g)</label>
                <input type="number" step="0.0001" min="0" inputmode="decimal"
                    name="lines[{{ $index }}][stones][{{ $sIndex }}][weight_grams]"
                    class="form-control est-stone-grams"
                    value="{{ $stone && (float) $stone->weight_grams > 0 ? (float) $stone->weight_grams : '' }}">
            </div>

            <div class="col-4 col-md-1">
                <label class="form-label fs-12 mb-1">PCS</label>
                {{-- step=1 so a stray decimal is caught in the box rather than as a
                     server error naming a row buried in a closed popup. --}}
                <input type="number" min="0" step="1" inputmode="numeric"
                    name="lines[{{ $index }}][stones][{{ $sIndex }}][pieces]"
                    class="form-control est-stone-pieces whole-number"
                    value="{{ $stone && $stone->pieces ? $stone->pieces : '' }}">
            </div>

            <div class="col-4 col-md-2">
                <label class="form-label fs-12 mb-1">RATE</label>
                <input type="number" step="0.01" min="0" inputmode="decimal"
                    name="lines[{{ $index }}][stones][{{ $sIndex }}][rate]"
                    class="form-control est-stone-rate" value="{{ $stone ? (float) $stone->rate : '' }}">
            </div>

            {{-- Wide enough for a five-figure amount; at col-md-1 it clipped to "276". --}}
            <div class="col-4 col-md-2">
                <label class="form-label fs-12 mb-1">Amount</label>
                <input type="text" class="form-control bg-light est-stone-amount" readonly tabindex="-1"
                    value="{{ $stone ? number_format((float) $stone->amount, 0, '.', '') : '' }}">
            </div>

            <div class="col-4 col-md-1">
                <label class="form-label fs-12 mb-1" title="Deduct this weight from the gross">Ded.</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="hidden" name="lines[{{ $index }}][stones][{{ $sIndex }}][deduct_from_gross]"
                        value="0">
                    <input type="checkbox" class="form-check-input mt-0 est-stone-deduct"
                        name="lines[{{ $index }}][stones][{{ $sIndex }}][deduct_from_gross]" value="1"
                        @checked($stone?->deduct_from_gross ?? true)>

                    <button type="button" class="btn btn-danger btn-icon est-stone-remove" title="Remove">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

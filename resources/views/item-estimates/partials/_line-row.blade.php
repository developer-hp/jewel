{{--
    One quoted piece.

    Every numeric box carries inputmode="decimal" so a tablet offers digits rather
    than a keyboard — this screen is used on glass.
--}}
<tr class="estimate-row" data-index="{{ $index }}">
    <td>
        <select name="lines[{{ $index }}][item_id]" class="form-select est-item">
            <option value="">Select Item</option>
            @if ($line?->item)
                {{-- The chosen piece, so the select round-trips without a lookup. --}}
                <option value="{{ $line->item->id }}" selected>{{ $line->item->code }} — {{ $line->item->name }}</option>
            @endif
        </select>
    </td>

    <td>
        <input type="text" name="lines[{{ $index }}][description]" class="form-control est-desc"
            value="{{ $line?->description }}" maxlength="255" placeholder="Description">
    </td>

    <td>
        <div class="input-group">
            <input type="number" step="0.001" min="0" inputmode="decimal"
                name="lines[{{ $index }}][gross_weight]" class="form-control est-gross"
                value="{{ $line ? (float) $line->gross_weight : '' }}">
            {{-- Enter opens the popup for the desk; this button opens it for a thumb. --}}
            <button type="button" class="btn btn-outline-secondary est-stones-open"
                title="Stones and diamonds">
                <i class="ri-vip-diamond-line"></i>
            </button>
        </div>
    </td>

    <td>
        <input type="text" class="form-control bg-light est-net" readonly tabindex="-1"
            value="{{ $line ? number_format($line->netWeight(), 3, '.', '') : '' }}">
    </td>

    <td>
        <input type="number" step="0.01" min="0" inputmode="decimal"
            name="lines[{{ $index }}][rate]" class="form-control est-rate"
            value="{{ $line ? (float) $line->rate : '' }}" title="Per ten grams">
    </td>

    <td>
        <input type="text" class="form-control bg-light est-jadtar" readonly tabindex="-1"
            value="{{ $line ? number_format($line->jadtar(), 0, '.', '') : '' }}">
    </td>

    <td>
        <div class="input-group">
            <input type="number" step="0.01" min="0" inputmode="decimal"
                name="lines[{{ $index }}][labour_amount]" class="form-control est-labour"
                value="{{ $line ? (float) $line->labour_amount : '' }}">
            <select name="lines[{{ $index }}][labour_type]" class="form-select est-labour-type"
                style="max-width: 4.5rem;" title="How the labour reads">
                @foreach (\App\Models\ItemEstimateLine::LABOUR_TYPES as $value => $label)
                    <option value="{{ $value }}"
                        @selected(($line?->labour_type ?? \App\Models\ItemEstimateLine::LABOUR_PER_GRAM) === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    </td>

    <td>
        <input type="number" step="0.01" min="0" inputmode="decimal"
            name="lines[{{ $index }}][oc_amount]" class="form-control est-oc"
            value="{{ $line ? (float) $line->oc_amount : '' }}">
    </td>

    <td>
        <input type="text" class="form-control bg-light est-total" readonly tabindex="-1"
            value="{{ $line ? number_format($line->total(), 2, '.', '') : '' }}">
    </td>

    <td class="text-center">
        <button type="button" class="btn btn-danger btn-icon est-remove" title="Remove row">
            <i class="ri-close-line"></i>
        </button>
    </td>

    {{-- The line's stones live here and are moved into the modal while it is open, so
         their input names never change and nothing has to be serialised. --}}
    <td class="d-none">
        <div class="est-stone-store" data-index="{{ $index }}">
            @foreach (($line?->stones ?? collect()) as $sIndex => $stone)
                @include('item-estimates.partials._stone-row', [
                    'index' => $index,
                    'sIndex' => $sIndex,
                    'stone' => $stone,
                    'stoneMasters' => $stoneMasters,
                ])
            @endforeach
        </div>
    </td>
</tr>

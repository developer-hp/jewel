@php
    $held = $line?->item;
    $ready = (bool) $held;
    $stones = $line?->stones ?? collect();
@endphp

{{--
    One piece on the order.

    A line with a piece held against it is locked: the reservation, and any rate
    pinned to it, must not move under an edit. Its id still posts so the server
    updates it in place rather than dropping and recreating it.
--}}
<tr class="order-row" data-index="{{ $index }}">
    <td>
        @if ($line?->id)
            <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
        @endif

        <span class="order-line-badge">Line <span class="order-line-no">{{ is_numeric($index) ? $index + 1 : '' }}</span></span>

        <select name="lines[{{ $index }}][source_item_id]" class="form-select form-select-sm order-item"
            @disabled($ready)>
            <option value="">— to be made —</option>
            @if ($line?->sourceItem)
                <option value="{{ $line->sourceItem->id }}" selected>
                    {{ $line->sourceItem->code }} — {{ $line->sourceItem->name }}
                </option>
            @endif
        </select>

        <div class="form-check form-switch mt-1">
            <input type="hidden" name="lines[{{ $index }}][made_to_order]" value="0">
            <input class="form-check-input order-make" type="checkbox" value="1"
                name="lines[{{ $index }}][made_to_order]"
                id="make-{{ $index }}" @checked($line?->made_to_order) @disabled($ready)>
            <label class="form-check-label fs-13" for="make-{{ $index }}">Make to order</label>
        </div>
    </td>

    <td>
        <input type="text" name="lines[{{ $index }}][description]" class="form-control order-description"
            value="{{ $line?->description }}" maxlength="255" placeholder="Description" @disabled($ready)>

        <div class="row g-1 mt-1">
            <div class="col-6">
                <select name="lines[{{ $index }}][metal_type_id]" class="form-select form-select-sm order-metal"
                    @disabled($ready)>
                    <option value="">Metal</option>
                    @foreach ($metalTypes as $metal)
                        <option value="{{ $metal->id }}" @selected($line?->metal_type_id == $metal->id)>{{ $metal->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <select name="lines[{{ $index }}][purity_id]" class="form-select form-select-sm order-purity"
                    data-selected="{{ $line?->purity_id }}" @disabled($ready)>
                    <option value="">Purity</option>
                </select>
            </div>
        </div>
    </td>

    <td>
        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][net_weight]"
            class="form-control" value="{{ $line ? (float) $line->net_weight : '' }}" @disabled($ready)>
    </td>

    <td>
        <input type="number" step="0.01" min="0" name="lines[{{ $index }}][lc_amount]"
            class="form-control" value="{{ $line ? (float) $line->lc_amount : '' }}" @disabled($ready)>
    </td>

    <td>
        <select name="lines[{{ $index }}][lc_type]" class="form-select" @disabled($ready)>
            @foreach (\App\Models\OrderFormLine::LC_TYPES as $value => $label)
                <option value="{{ $value }}" @selected(($line?->lc_type ?? 'per_gram') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>

    {{-- Totalled from the stones and the chosen piece's extra charges as a starting
         figure, then left alone the moment it is typed in. --}}
    <td>
        <input type="number" step="0.01" min="0" name="lines[{{ $index }}][oc_amount]"
            class="form-control order-oc" value="{{ $line ? (float) $line->oc_amount : '' }}"
            data-touched="{{ $line ? '1' : '0' }}" @disabled($ready)>
        <small class="text-muted fs-12 order-oc-hint d-none"></small>
    </td>

    <td>
        <input type="text" name="lines[{{ $index }}][size_pcs]" class="form-control"
            value="{{ $line?->size_pcs }}" maxlength="50" @disabled($ready)>
    </td>

    <td class="text-center">
        @if ($held)
            <span class="badge bg-success-subtle text-success d-block">{{ $held->code }}</span>
        @elseif ($line?->made_to_order || ! $line)
            {{-- A piece still to be made cannot be conjured from a grid row; it is
                 created on the Order Items screen, which sets the link. --}}
            <span class="text-muted fs-13 order-make-note">make it</span>
            <div class="form-check d-none order-reserve-wrap">
                <input type="hidden" name="lines[{{ $index }}][reserve]" value="0">
                <input class="form-check-input order-reserve" type="checkbox" value="1"
                    name="lines[{{ $index }}][reserve]">
            </div>
        @else
            <div class="form-check order-reserve-wrap">
                <input type="hidden" name="lines[{{ $index }}][reserve]" value="0">
                <input class="form-check-input order-reserve" type="checkbox" value="1"
                    name="lines[{{ $index }}][reserve]" title="Hold this piece for the customer">
            </div>
            <span class="text-muted fs-13 order-make-note d-none">make it</span>
        @endif
    </td>

    {{-- The rate is agreed with the customer as the order is taken, so it is pinned
         here rather than on a later visit to the edit screen. Never disabled: a held
         line's rate may still be settled afterwards. --}}
    <td class="text-center">
        <div class="form-check d-inline-block">
            <input type="hidden" name="lines[{{ $index }}][fix_rate]" value="0">
            <input class="form-check-input order-fix-rate" type="checkbox" value="1"
                id="fix-rate-{{ $index }}" name="lines[{{ $index }}][fix_rate]"
                @checked($line?->isRateFixed()) title="Pin today's rate for this line">
        </div>

        @if ($line?->isRateFixed())
            <span class="badge bg-success d-block mt-1">{{ $line->rateLabel() }}</span>
            <small class="text-muted fs-12">{{ $line->rate_fixed_at?->format('d-m-Y') }}</small>
        @else
            <small class="text-muted fs-12 d-block">today's rate</small>
        @endif
    </td>

    <td class="text-center">
        @unless ($ready)
            <button type="button" class="btn btn-sm btn-danger btn-icon order-remove" title="Remove row">
                <i class="ri-close-line"></i>
            </button>
        @endunless
    </td>
</tr>

{{-- The stones and diamonds asked for. Loaded from the chosen piece, editable until
     one is held against the line. --}}
<tr class="order-stone-row">
    <td colspan="10">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="fs-13 text-muted">Stones &amp; diamonds</span>
            @unless ($ready)
                <button type="button" class="btn btn-sm btn-soft-secondary order-add-stone">
                    <i class="ri-add-line"></i> Add stone
                </button>
            @endunless
        </div>

        <table class="table table-sm table-borderless mb-0">
            <tbody class="order-stone-body" data-index="{{ $index }}">
                @foreach ($stones as $s => $stone)
                    @include('order-forms.partials._stone-row', [
                        'index' => $index,
                        'sIndex' => $s,
                        'stone' => $stone,
                        'ready' => $ready,
                    ])
                @endforeach
            </tbody>
        </table>
    </td>
</tr>

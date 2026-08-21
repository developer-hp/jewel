@csrf

@php($caratToGram = \App\Models\Item::CARAT_TO_GRAM)

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Item Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="item_group_id" class="form-label">Item Group <span class="text-danger">*</span></label>
                        <select id="item_group_id" name="item_group_id"
                            class="form-select @error('item_group_id') is-invalid @enderror" required
                            @disabled($item->exists)>
                            <option value="">Select…</option>
                            @foreach ($groups as $group)
                                {{-- data-name carries the bare group name; the visible
                                     text also has the prefix, which must not reach the
                                     item name. --}}
                                <option value="{{ $group->id }}" data-next="{{ $group->previewNextCode() }}"
                                    data-name="{{ $group->name }}"
                                    @selected(old('item_group_id', $item->item_group_id) == $group->id)>
                                    {{ $group->name }} ({{ $group->prefix }})
                                </option>
                            @endforeach
                        </select>
                        @if ($item->exists)
                            {{-- Disabled selects are not submitted; post the value so validation passes.
                                 The controller ignores it on update — the group is fixed once a code is issued. --}}
                            <input type="hidden" name="item_group_id" value="{{ $item->item_group_id }}">
                            <small class="text-muted">Locked — the item code is derived from this group.</small>
                        @else
                            <small class="text-muted">
                                Code will be <code id="code-preview">auto-generated on save</code>.
                            </small>
                        @endif
                        @error('item_group_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Item Code</label>
                        <input type="text" class="form-control"
                            value="{{ $item->code ?? 'Auto-generated on save' }}" disabled>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="huid" class="form-label">HUID</label>
                        <input type="text" id="huid" name="huid"
                            class="form-control text-uppercase @error('huid') is-invalid @enderror"
                            value="{{ old('huid', $item->huid) }}" maxlength="20" placeholder="Hallmark code">
                        @error('huid')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $item->name) }}" placeholder="Antique Jadtar Necklace" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id"
                            class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">None — in-house</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    @selected(old('supplier_id', $item->supplier_id) == $supplier->id)>
                                    {{ $supplier->label() }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Optional.</small>
                        @error('supplier_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="metal_type_id" class="form-label">Metal Type <span class="text-danger">*</span></label>
                        <select id="metal_type_id" name="metal_type_id"
                            class="form-select @error('metal_type_id') is-invalid @enderror" required>
                            <option value="">Select…</option>
                            @foreach ($metalTypes as $metalType)
                                <option value="{{ $metalType->id }}"
                                    @selected(old('metal_type_id', $item->metal_type_id) == $metalType->id)>
                                    {{ $metalType->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('metal_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="purity_id" class="form-label">Purity <span class="text-danger">*</span></label>
                        <select id="purity_id" name="purity_id"
                            class="form-select @error('purity_id') is-invalid @enderror" required>
                            <option value="">Select metal type first</option>
                        </select>
                        @error('purity_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="making_charge_id" class="form-label">Making Charge</label>
                        <select id="making_charge_id" name="making_charge_id" class="form-select">
                            <option value="">None</option>
                            @foreach ($makingCharges as $charge)
                                <option value="{{ $charge->id }}"
                                    @selected(old('making_charge_id', $item->making_charge_id) == $charge->id)>
                                    {{ $charge->code }} — {{ $charge->summary() }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Applied when the item is quoted.</small>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="gross_weight" class="form-label">Gross Weight (g) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" min="0.001" id="gross_weight" name="gross_weight"
                            class="form-control @error('gross_weight') is-invalid @enderror"
                            value="{{ old('gross_weight', $item->gross_weight) }}" required>
                        @error('gross_weight')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="other_deduction" class="form-label">Other Deduction (g)</label>
                        <input type="number" step="0.001" min="0" id="other_deduction" name="other_deduction"
                            class="form-control @error('other_deduction') is-invalid @enderror"
                            value="{{ old('other_deduction', $item->other_deduction ?? 0) }}">
                        <small class="text-muted">Wax, lac or thread on antique pieces.</small>
                        @error('other_deduction')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 d-none">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                @checked(old('is_active', $item->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-light-subtle border">
            <div class="card-header py-2">
                <h5 class="mb-0">Live Summary</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-2">
                    <tbody>
                        <tr>
                            <td>Gross weight</td>
                            <td class="text-end"><span id="sum-gross">0.000</span> g</td>
                        </tr>
                        <tr>
                            <td>Less: stones</td>
                            <td class="text-end text-danger">−<span id="sum-stone">0.000</span> g</td>
                        </tr>
                        <tr>
                            <td>Less: diamonds</td>
                            <td class="text-end text-danger">−<span id="sum-diamond">0.000</span> g</td>
                        </tr>
                        <tr>
                            <td>Less: other</td>
                            <td class="text-end text-danger">−<span id="sum-other">0.000</span> g</td>
                        </tr>
                        <tr class="table-active">
                            <th>Net weight</th>
                            <th class="text-end"><span id="sum-net">0.000</span> g</th>
                        </tr>
                    </tbody>
                </table>

                <div id="net-warning" class="alert alert-danger py-2 fs-13 d-none">
                    <i class="ri-error-warning-line me-1"></i>
                    Deductions exceed the gross weight — this will be rejected on save.
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td>Metal value <small class="text-muted">(today's rate)</small></td>
                            <td class="text-end">₹<span id="sum-metal">0.00</span></td>
                        </tr>
                        <tr>
                            <td>Stone + diamond value</td>
                            <td class="text-end">₹<span id="sum-stone-value">0.00</span></td>
                        </tr>
                        <tr>
                            <td>Extra charges</td>
                            <td class="text-end">₹<span id="sum-extra">0.00</span></td>
                        </tr>
                    </tbody>
                </table>

                <p class="text-muted fs-12 mb-0 mt-2">
                    Indicative only. Making charge is applied at quotation time.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- Stones and diamonds live in popups; these buttons open them and report what is
     inside, so a piece with no stones costs nothing but a glance. --}}
<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Stones &amp; Diamonds</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ([['stone', 'Stones', 'ri-shining-2-fill'], ['diamond', 'Diamonds', 'ri-vip-diamond-fill']] as [$section, $label, $icon])
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#{{ $section }}-modal">
                            <i class="{{ $icon }}"></i> {{ $label }}
                        </button>
                        <div class="text-muted fs-13" id="{{ $section }}-trigger-summary">No {{ strtolower($label) }} added</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@include('items.partials._stone-section', [
    'section' => 'stone',
    'title' => 'Stones',
    'masters' => $stoneMasters,
    'rows' => $stoneRows,
    'offset' => 0,
])

@include('items.partials._stone-section', [
    'section' => 'diamond',
    'title' => 'Diamonds',
    'masters' => $diamondMasters,
    'rows' => $diamondRows,
    'offset' => 1000,
])

<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Extra Charges</h5>
    </div>
    <div class="card-body">
        <p class="text-muted fs-13">
            One-off costs such as polish or certification. Stored on the item and
            applied at quotation time; the caption prints on the tag.
        </p>

        <div class="row">
            @foreach ([1, 2] as $slot)
                <div class="col-md-3 mb-3">
                    <label for="extra_charge_{{ $slot }}_label" class="form-label">Charge {{ $slot }} Caption</label>
                    <input type="text" id="extra_charge_{{ $slot }}_label"
                        name="extra_charge_{{ $slot }}_label"
                        class="form-control @error("extra_charge_{$slot}_label") is-invalid @enderror"
                        value="{{ old("extra_charge_{$slot}_label", $item->{"extra_charge_{$slot}_label"}) }}"
                        maxlength="20" placeholder="E{{ $slot }}">
                    @error("extra_charge_{$slot}_label")
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3 mb-3">
                    <label for="extra_charge_{{ $slot }}" class="form-label">Charge {{ $slot }} Amount</label>
                    <input type="number" step="0.01" min="0" id="extra_charge_{{ $slot }}"
                        name="extra_charge_{{ $slot }}"
                        class="form-control extra-charge @error("extra_charge_{$slot}") is-invalid @enderror"
                        value="{{ old("extra_charge_{$slot}", $item->{"extra_charge_{$slot}"} ?? 0) }}">
                    @error("extra_charge_{$slot}")
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
            <div class="col-12 mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $item->description) }}</textarea>
            </div>

        </div>
        
    </div>
</div>

<div class="mb-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $item->exists ? 'Update' : 'Create' }} Item
    </button>
    <a href="{{ route('items.index') }}" class="btn btn-light">Cancel</a>
</div>

@include('items.partials._form-script')

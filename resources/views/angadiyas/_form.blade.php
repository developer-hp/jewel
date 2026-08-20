@csrf

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">{{ $angadiya->exists ? 'Edit' : 'Create' }} Angadiya</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-select">
                            <option value="">— one-off recipient —</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" data-name="{{ $supplier->name }}"
                                    data-city="{{ $supplier->city }}" data-mobile="{{ $supplier->phone }}"
                                    @selected(old('supplier_id', $angadiya->supplier_id) == $supplier->id)>
                                    {{ $supplier->short_name ?: $supplier->name }}@if ($supplier->city) ({{ $supplier->city }})@endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            Optional — picking one fills the three fields below, which stay editable.
                            What you save here is kept on the slip, so changing the supplier later
                            will not alter it.
                        </small>
                        @error('supplier_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $angadiya->name) }}" maxlength="150" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                        <input type="text" id="city" name="city"
                            class="form-control @error('city') is-invalid @enderror"
                            value="{{ old('city', $angadiya->city) }}" maxlength="100" required>
                        @error('city')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="mobile" class="form-label">Mobile <span class="text-danger">*</span></label>
                        <input type="text" id="mobile" name="mobile"
                            class="form-control @error('mobile') is-invalid @enderror"
                            value="{{ old('mobile', $angadiya->mobile) }}" maxlength="30" required>
                        @error('mobile')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="insurance_amount" class="form-label">Insurance <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" id="insurance_amount" name="insurance_amount"
                            class="form-control @error('insurance_amount') is-invalid @enderror"
                            value="{{ old('insurance_amount', $angadiya->insurance_amount) }}" required>
                        @error('insurance_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <label for="remark" class="form-label">Remark</label>
                        <input type="text" id="remark" name="remark"
                            class="form-control @error('remark') is-invalid @enderror"
                            value="{{ old('remark', $angadiya->remark) }}" maxlength="500">
                        <small class="text-muted">Kept in the register; it does not print on the slip.</small>
                        @error('remark')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('angadiyas.index') }}" class="btn btn-light">Cancel</a>

                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line"></i> {{ $angadiya->exists ? 'Update' : 'Save' }}
                    </button>

                    @can('angadiya.print')
                        @unless ($angadiya->exists)
                            <button type="submit" name="print_after_save" value="1" class="btn btn-primary">
                                <i class="ri-printer-line"></i> Save &amp; Print
                            </button>
                        @endunless
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        @php($from = $angadiya->fromBlock())
        <div class="card bg-light-subtle border">
            <div class="card-header py-2">
                <h5 class="mb-0">Slip Preview</h5>
            </div>
            <div class="card-body">
                <div class="border border-dark p-2" style="font-family: Helvetica, Arial, sans-serif;">
                    <div class="fw-bold">INSURANCE : <span id="pv-insurance">0</span></div>
                    <div class="fw-bold">TO : <span id="pv-city">—</span></div>
                    <div class="fw-bold" id="pv-name">—</div>
                    <div class="fw-bold" id="pv-mobile">—</div>
                    <hr class="border-dark my-2">
                    <div class="fw-bold fs-12">FROM</div>
                    @if ($from)
                        <div class="fw-bold">{{ $from['name'] }}</div>
                        <div class="fw-bold">{{ $from['phone'] }}</div>
                    @else
                        <div class="text-danger fs-12 fst-italic">
                            Set Firm Details under
                            @can('app_setting.view')
                                <a href="{{ route('app-settings.edit') }}">Appearance</a>
                            @else
                                Appearance
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

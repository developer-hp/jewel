@csrf

@php($isDiamond = $kind === \App\Models\StoneMaster::KIND_DIAMOND)

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">{{ $singular }} Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $stone->name) }}"
            placeholder="{{ $isDiamond ? 'Round Brilliant VVS' : 'Ruby, Emerald, Kundan' }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="code" class="form-label">Code</label>
        <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
            value="{{ old('code', $stone->code) }}" placeholder="{{ $isDiamond ? 'DI-RBVVS' : 'ST-RUBY' }}"
            maxlength="30">
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="shape" class="form-label">Shape</label>
        <input type="text" id="shape" name="shape" class="form-control" value="{{ old('shape', $stone->shape) }}"
            placeholder="{{ $isDiamond ? 'Round, Princess' : 'Oval' }}">
    </div>

    <div class="col-md-3 mb-3">
        <label for="quality" class="form-label">{{ $isDiamond ? 'Clarity' : 'Quality' }}</label>
        <input type="text" id="quality" name="quality" class="form-control"
            value="{{ old('quality', $stone->quality) }}" placeholder="{{ $isDiamond ? 'VVS, VS, SI' : 'AAA' }}">
    </div>

    <div class="col-md-3 mb-3">
        <label for="colour" class="form-label">Colour</label>
        <input type="text" id="colour" name="colour" class="form-control"
            value="{{ old('colour', $stone->colour) }}" placeholder="{{ $isDiamond ? 'D, EF, FG' : 'Red' }}">
    </div>

    <div class="col-md-3 mb-3">
        <label for="size" class="form-label">Size / Sieve</label>
        <input type="text" id="size" name="size" class="form-control" value="{{ old('size', $stone->size) }}"
            placeholder="{{ $isDiamond ? '0.10-0.15' : '2mm' }}">
    </div>

    <div class="col-md-4 mb-3">
        <label for="rate_unit" class="form-label">Rate Unit <span class="text-danger">*</span></label>
        <select id="rate_unit" name="rate_unit" class="form-select @error('rate_unit') is-invalid @enderror" required>
            @foreach (\App\Models\StoneMaster::RATE_UNITS as $value => $label)
                <option value="{{ $value }}" @selected(old('rate_unit', $stone->rate_unit) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <small class="text-muted">How the amount is worked out on an item line.</small>
        @error('rate_unit')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="default_rate" class="form-label">Cost Rate (₹) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" id="default_rate" name="default_rate"
            class="form-control @error('default_rate') is-invalid @enderror"
            value="{{ old('default_rate', $stone->default_rate ?? 0) }}" min="0" required>
        <small class="text-muted">Prefills item lines; the rate is snapshotted per item.</small>
        @error('default_rate')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="sale_rate" class="form-label">Sale Rate (₹)</label>
        <input type="number" step="0.01" id="sale_rate" name="sale_rate"
            class="form-control @error('sale_rate') is-invalid @enderror"
            value="{{ old('sale_rate', $stone->sale_rate) }}" min="0"
            placeholder="Same as cost rate">
        <small class="text-muted">Leave blank to charge the cost rate. Used when quoting.</small>
        @error('sale_rate')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $stone->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $stone->exists ? 'Update' : 'Create' }} {{ $singular }}
    </button>
    <a href="{{ route($routePrefix . '.index') }}" class="btn btn-light">Cancel</a>
</div>

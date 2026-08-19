@csrf

@php($hasItems = $group->exists && $group->items()->exists())

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Group Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $group->name) }}" placeholder="Ring, Necklace, Bangle" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="prefix" class="form-label">Code Prefix <span class="text-danger">*</span></label>
        <input type="text" id="prefix" name="prefix"
            class="form-control text-uppercase @error('prefix') is-invalid @enderror"
            value="{{ old('prefix', $group->prefix) }}" placeholder="RNG" maxlength="10" required
            @disabled($hasItems)>
        @if ($hasItems)
            {{-- Disabled inputs are not submitted; post the existing value so validation passes. --}}
            <input type="hidden" name="prefix" value="{{ $group->prefix }}">
            <small class="text-warning">Locked — this group already has items.</small>
        @endif
        @error('prefix')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="code_padding" class="form-label">Number Length <span class="text-danger">*</span></label>
        <input type="number" id="code_padding" name="code_padding"
            class="form-control @error('code_padding') is-invalid @enderror"
            value="{{ old('code_padding', $group->code_padding ?? 4) }}" min="1" max="10" required>
        <small class="text-muted">4 &rarr; <code id="code-preview">{{ $group->exists ? $group->previewNextCode() : 'RNG0001' }}</code></small>
        @error('code_padding')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="metal_type_id" class="form-label">Metal Type</label>
        <select id="metal_type_id" name="metal_type_id" class="form-select @error('metal_type_id') is-invalid @enderror">
            <option value="">Any</option>
            @foreach ($metalTypes as $id => $name)
                <option value="{{ $id }}" @selected(old('metal_type_id', $group->metal_type_id) == $id)>{{ $name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Optional — for reference only, it does not restrict item entry.</small>
        @error('metal_type_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="sort_order" class="form-label">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" class="form-control"
            value="{{ old('sort_order', $group->sort_order ?? 0) }}" min="0">
    </div>

    @if ($group->exists)
        <div class="col-12 mb-3">
            <div class="alert alert-info mb-0">
                <i class="ri-information-line me-1"></i>
                Next code issued from this group: <strong>{{ $group->previewNextCode() }}</strong>
                (sequence {{ $group->next_sequence }}).
            </div>
        </div>
    @endif

    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                @checked(old('is_active', $group->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $group->exists ? 'Update' : 'Create' }} Item Group
    </button>
    <a href="{{ route('item-groups.index') }}" class="btn btn-light">Cancel</a>
</div>

@push('js')
    <script>
        $(function () {
            const sequence = {{ $group->next_sequence ?? 1 }};

            function refreshPreview() {
                const prefix = ($('#prefix').val() || 'RNG').toUpperCase();
                const padding = parseInt($('#code_padding').val(), 10) || 4;
                $('#code-preview').text(prefix + String(sequence).padStart(padding, '0'));
            }

            $('#prefix, #code_padding').on('input', refreshPreview);
            refreshPreview();
        });
    </script>
@endpush

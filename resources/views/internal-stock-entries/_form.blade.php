@csrf

@php($weight = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0')

<div class="row">
    <div class="col-md-8 mb-3">
        <label for="internal_stock_id" class="form-label">Internal Stock <span class="text-danger">*</span></label>
        <div class="d-flex align-items-center gap-3">
            <select id="internal_stock_id" name="internal_stock_id"
                class="form-select @error('internal_stock_id') is-invalid @enderror" required>
                <option value="">Select</option>
                @foreach ($stocks as $stock)
                    <option value="{{ $stock->id }}" data-balance="{{ $weight($stock->balance()) }}"
                        @selected(old('internal_stock_id', $entry->internal_stock_id) == $stock->id)>
                        {{ $stock->name }}
                    </option>
                @endforeach
            </select>
            {{-- What the pot holds right now, so an Out is not guessed at. --}}
            <span class="text-warning text-nowrap fw-semibold" id="stock-balance"></span>
        </div>
        @error('internal_stock_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8 mb-3">
        <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $entry->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <small class="text-muted">Opening adds to the balance just as In does.</small>
        @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8 mb-3">
        <label for="weight" class="form-label">Weight <span class="text-danger">*</span></label>
        <input type="number" step="0.001" min="0" id="weight" name="weight"
            class="form-control @error('weight') is-invalid @enderror"
            value="{{ old('weight', $entry->weight ? (float) $entry->weight : '') }}" required>
        @error('weight')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8 mb-3">
        <label for="note" class="form-label">Note <span class="text-danger">*</span></label>
        <input type="text" id="note" name="note" class="form-control @error('note') is-invalid @enderror"
            value="{{ old('note', $entry->note) }}" maxlength="255" required>
        @error('note')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex gap-2">
    <a href="{{ route('internal-stock-entries.index') }}" class="btn btn-warning">Cancel</a>
    <button type="submit" class="btn btn-dark">
        <i class="ri-save-line"></i> Save
    </button>
</div>

@push('js')
    <script>
        $(function () {
            function showBalance() {
                const balance = $('#internal_stock_id option:selected').data('balance');

                $('#stock-balance').text(balance === undefined ? '' : 'Balance : ' + balance);
            }

            $('#internal_stock_id').on('change', showBalance);
            showBalance();
        });
    </script>
@endpush

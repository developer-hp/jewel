{{-- Set straight from the grid, as on the old screen, rather than opening the row. --}}
@can('internal_stock.edit')
    <div class="d-inline-flex gap-3 reset-toggle" data-url="{{ route('internal-stocks.reset-toggle', $stock) }}">
        @foreach ([1 => 'Yes', 0 => 'No'] as $value => $label)
            <div class="form-check mb-0">
                <input class="form-check-input" type="radio" value="{{ $value }}"
                    name="reset-{{ $stock->id }}" id="reset-{{ $stock->id }}-{{ $value }}"
                    @checked($stock->reset_on_opening === (bool) $value)>
                <label class="form-check-label" for="reset-{{ $stock->id }}-{{ $value }}">{{ $label }}</label>
            </div>
        @endforeach
    </div>
@else
    <span class="badge {{ $stock->reset_on_opening ? 'bg-success' : 'bg-secondary' }}">
        {{ $stock->reset_on_opening ? 'Yes' : 'No' }}
    </span>
@endcan

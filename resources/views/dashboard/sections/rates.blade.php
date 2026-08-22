<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Today's Rates</h5>
        @can('metal_rate.create')
            <a href="{{ route('rates.today') }}" class="btn btn-sm btn-soft-primary">Enter Rates</a>
        @endcan
    </div>
    <div class="card-body">
        @if ($section['missing'] > 0)
            <div class="alert alert-warning d-flex justify-content-between align-items-center py-2">
                <div>
                    <i class="ri-alarm-warning-fill me-1"></i>
                    <strong>{{ $section['missing'] }}</strong>
                    {{ Str::plural('purity', $section['missing']) }}
                    {{ $section['missing'] === 1 ? 'has' : 'have' }} no rate for today.
                    Items will price against the last recorded rate until you enter them.
                </div>
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2">
            @foreach ($section['rows'] as $row)
                <div class="border rounded px-3 py-2 {{ $row->rated ? '' : 'border-warning' }}">
                    <div class="text-muted fs-12">{{ $row->label }}</div>
                    <div class="fs-16 fw-semibold">
                        @if ($row->rate)
                            {{ number_format((float) $row->rate, 2) }}
                            @unless ($row->rated)
                                <span class="badge bg-warning text-dark ms-1">old</span>
                            @endunless
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

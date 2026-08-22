@php
    $wt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    $palette = ['bg-info', 'bg-primary', 'bg-warning', 'bg-secondary', 'bg-success', 'bg-danger'];
@endphp

<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Internal Stock</h5>
        <a href="{{ route('internal-stock-entries.index') }}" class="btn btn-sm btn-soft-primary">Ledger</a>
    </div>
    <div class="card-body">
        <div class="row row-cols-2 row-cols-md-4 g-3">
            @foreach ($section['stocks'] as $stock)
                <div class="col">
                    <div class="card {{ $palette[$loop->index % count($palette)] }} h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-white text-uppercase mb-1 fs-12">{{ $stock->name }}</h6>
                            <h4 class="text-white mb-0">{{ $wt($stock->balance()) }} GM</h4>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

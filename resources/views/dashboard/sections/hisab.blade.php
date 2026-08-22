@php
    $wt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    $money = fn ($v) => number_format((float) $v, 0);
@endphp

<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Today's Supplier Hisab</h5>
        <a href="{{ route('supplier-hisabs.index') }}" class="btn btn-sm btn-soft-primary">Open</a>
    </div>
    <div class="card-body">
        <div class="row row-cols-2 row-cols-md-4 g-3">
            @foreach ([
                ['label' => 'Fine Baki', 'value' => $wt($section['fine_baki']).' g'],
                ['label' => 'Cash Baki', 'value' => $money($section['cash_baki'])],
                ['label' => 'Fine Kapi', 'value' => $wt($section['fine_kapi']).' g'],
                ['label' => 'Cash Apvi', 'value' => $money($section['cash_apvi'])],
            ] as $figure)
                <div class="col">
                    <div class="text-muted fs-12 text-uppercase">{{ $figure['label'] }}</div>
                    <div class="fs-18 fw-semibold">{{ $figure['value'] }}</div>
                </div>
            @endforeach
        </div>

        <p class="text-muted fs-13 mt-3 mb-0">
            {{ $section['count'] }} {{ Str::plural('entry', $section['count']) }} today,
            <strong>{{ $section['unsettled'] }}</strong> still unsettled.
        </p>
    </div>
</div>

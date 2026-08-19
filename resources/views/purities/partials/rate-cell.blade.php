@php($rate = $purity->rateOn())

@if ($rate)
    <strong>₹{{ number_format((float) $rate->rate_per_gram, 2) }}</strong> <span class="text-muted">/ g</span>
    <div class="text-muted fs-12">
        ₹{{ number_format((float) $rate->rate, 2) }} per {{ rtrim(rtrim(number_format((float) $rate->per_grams, 3, '.', ''), '0'), '.') }} g
        · {{ $rate->effective_date->format('d M Y') }}
    </div>
@else
    <span class="badge bg-warning-subtle text-warning">No rate set</span>
@endif

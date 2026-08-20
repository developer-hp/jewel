@php($entered = $lot->grossEntered())

<div><strong>{{ number_format($entered, 3) }}</strong> <span class="text-muted fs-12">g</span></div>

@if ($lot->total_gross_weight !== null)
    <small class="{{ $lot->exceedsGrossTarget() ? 'text-danger' : 'text-muted' }}">
        of {{ number_format((float) $lot->total_gross_weight, 3) }} g
        @if ($lot->exceedsGrossTarget())
            <i class="ri-alert-line" title="Over the declared total"></i>
        @endif
    </small>
@else
    <small class="text-muted">no target</small>
@endif

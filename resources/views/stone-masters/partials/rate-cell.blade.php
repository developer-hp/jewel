<div>
    <strong>₹{{ number_format((float) $stone->default_rate, 2) }}</strong>
    <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $stone->rateUnitLabel() }}</span>
</div>

@if ($stone->tracksCostRate())
    <small class="text-muted">Sale tracks cost</small>
@else
    <small class="{{ $stone->effectiveSaleRate() > (float) $stone->default_rate ? 'text-success' : 'text-warning' }}">
        Sale ₹{{ number_format($stone->effectiveSaleRate(), 2) }}
    </small>
@endif

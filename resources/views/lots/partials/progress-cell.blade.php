@php($expected = $lot->tagsExpected())
@php($used = $lot->items_count ?? $lot->tagsUsed())
@php($percent = $expected > 0 ? min(100, round(($used / $expected) * 100)) : 0)

<div class="d-flex justify-content-between fs-12">
    <span><strong>{{ $used }}</strong> / {{ $expected }} tags</span>
    <span class="text-muted">{{ $percent }}%</span>
</div>
<div class="progress mt-1" style="height: 5px;">
    <div class="progress-bar bg-{{ $lot->statusVariant() }}" style="width: {{ $percent }}%"></div>
</div>

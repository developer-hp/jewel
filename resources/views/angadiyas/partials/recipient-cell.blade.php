<div class="fw-semibold">{{ $angadiya->name }}</div>
<small class="text-muted">
    {{ $angadiya->city }} · {{ $angadiya->mobile }}
    @if ($angadiya->supplier)
        <span class="badge bg-light text-dark border ms-1">{{ $angadiya->supplier->short_name ?: $angadiya->supplier->name }}</span>
    @endif
</small>

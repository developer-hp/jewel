@php($parts = array_filter([$stone->shape, $stone->quality, $stone->colour, $stone->size]))

@forelse ($parts as $part)
    <span class="badge bg-light text-dark border">{{ $part }}</span>
@empty
    <span class="text-muted">—</span>
@endforelse

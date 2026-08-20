@forelse ($lot->lines as $line)
    <span class="badge bg-light text-dark border">
        {{ $line->itemGroup?->name ?? '—' }} {{ $line->pieces }}/{{ $line->tags }}
    </span>
@empty
    <span class="text-muted">—</span>
@endforelse

@if ($item->photoUrl())
    <img src="{{ $item->photoUrl() }}" alt="{{ $item->code }}" class="rounded"
        style="width: 36px; height: 36px; object-fit: cover;">
@else
    <span class="text-muted" title="No photo"><i class="ri-image-line fs-18"></i></span>
@endif

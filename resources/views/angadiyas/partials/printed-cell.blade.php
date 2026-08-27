@if ($angadiya->isPrinted())
    <span class="badge bg-success-subtle text-success" title="{{ $angadiya->printed_at->format('d-m-Y H:i') }}">
        {{ $angadiya->printed_at->format('d-m-Y') }}
    </span>
@else
    <span class="badge bg-warning-subtle text-warning">Not printed</span>
@endif

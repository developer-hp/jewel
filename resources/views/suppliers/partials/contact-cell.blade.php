@if ($supplier->phone)
    <div><i class="ri-phone-line me-1 text-muted"></i>{{ $supplier->phone }}</div>
@endif

@if ($supplier->address)
    <small class="text-muted">{{ Str::limit($supplier->address, 60) }}</small>
@endif

@unless ($supplier->phone || $supplier->address)
    <span class="text-muted">—</span>
@endunless

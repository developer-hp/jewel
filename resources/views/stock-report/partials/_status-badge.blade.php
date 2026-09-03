@if ($item->sold_at)
    <span class="badge bg-secondary">Sold</span>
@else
    <span class="badge bg-success">Active</span>
@endif

<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Quick Actions</h5>
    </div>
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($section['actions'] as $action)
                <a href="{{ $action->url }}" class="btn btn-soft-primary">
                    <i class="{{ $action->icon }}"></i> {{ $action->label }}
                </a>
            @endforeach
        </div>
    </div>
</div>

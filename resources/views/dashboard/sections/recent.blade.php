<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Recent Activity</h5>
    </div>
    <div class="card-body">
        <ul class="list-group list-group-flush">
            @foreach ($section['events'] as $event)
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <a href="{{ $event->url }}" class="text-reset text-decoration-none">
                        <i class="{{ $event->icon }} me-1 text-muted"></i> {{ $event->label }}
                    </a>
                    <span class="text-muted fs-12">{{ $event->at->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>

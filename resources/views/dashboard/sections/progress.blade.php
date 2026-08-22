<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Repairs &amp; Orders</h5>
    </div>
    <div class="card-body">
        <div class="row g-4">
            @foreach ($section['bars'] as $bar)
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <a href="{{ $bar->url }}" class="fw-semibold text-reset">{{ $bar->label }}</a>
                        <span class="text-muted fs-13">
                            <span class="text-danger">{{ number_format($bar->pending) }}</span> pending
                            &middot;
                            <span class="text-success">{{ number_format($bar->done) }}</span> done
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar"
                            style="width: {{ $bar->percent }}%"
                            aria-valuenow="{{ $bar->percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="text-muted fs-12 mt-1">
                        {{ number_format($bar->done) }} of {{ number_format($bar->total) }} finished
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

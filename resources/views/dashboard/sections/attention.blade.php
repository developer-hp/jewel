@php($urgent = $section['lines']->where('count', '>', 0))

<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">
            Needs Attention
            @if ($urgent->isEmpty())
                <span class="badge bg-success ms-1">all clear</span>
            @endif
        </h5>
    </div>
    <div class="card-body">
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-2">
            @foreach ($section['lines'] as $line)
                <div class="col">
                    <a href="{{ $line->url }}"
                        class="d-block border rounded p-2 text-reset text-decoration-none h-100 {{ $line->count > 0 ? 'border-danger' : '' }}">
                        <div class="fs-22 fw-bold {{ $line->count > 0 ? 'text-danger' : 'text-muted' }}">
                            {{ number_format($line->count) }}
                        </div>
                        <div class="text-muted fs-12">{{ $line->label }}</div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>

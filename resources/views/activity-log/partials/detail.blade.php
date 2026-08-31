{{--
    One activity row in full.

    The before/after table is the reason this screen exists: "who changed this rate"
    should be answerable at a glance, not by reading raw JSON out of a column.
--}}
@php
    [$label, $badge] = $row->logBadge();
    $changes = $row->changes();
    $context = $row->context();

    // A value can be null, a bool, or an array (a json column). Rendered here rather
    // than in the loop so both columns show it the same way.
    $show = function ($value) {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    };
@endphp

<dl class="row mb-3">
    <dt class="col-sm-3 text-muted fs-13">When</dt>
    <dd class="col-sm-9">{{ $row->created_at->format('d-m-Y H:i:s') }}</dd>

    <dt class="col-sm-3 text-muted fs-13">User</dt>
    <dd class="col-sm-9">
        {{ $row->causer?->name ?? 'System' }}
        @unless ($row->causer)
            {{-- A null causer is not a gap: it means nobody was signed in. --}}
            <small class="text-muted d-block fs-12">A scheduled task or an artisan command.</small>
        @endunless
    </dd>

    <dt class="col-sm-3 text-muted fs-13">Type</dt>
    <dd class="col-sm-9"><span class="badge {{ $badge }}">{{ $label }}</span></dd>

    <dt class="col-sm-3 text-muted fs-13">Action</dt>
    <dd class="col-sm-9">{{ $row->event ?? '—' }}</dd>

    <dt class="col-sm-3 text-muted fs-13">Subject</dt>
    <dd class="col-sm-9">{{ $row->subjectLabel() }}</dd>

    <dt class="col-sm-3 text-muted fs-13">Description</dt>
    <dd class="col-sm-9">{{ $row->description }}</dd>
</dl>

@if ($changes !== [])
    <h6 class="mb-2">What changed</h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 26%">Field</th>
                    <th>Before</th>
                    <th>After</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($changes as $field => $pair)
                    <tr>
                        <td class="fw-semibold">{{ $field }}</td>
                        <td class="text-muted">{{ $show($pair['old'] ?? null) }}</td>
                        <td class="fw-semibold">{{ $show($pair['new'] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($context !== [])
    <h6 class="mb-2">Context</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
            <tbody>
                @foreach ($context as $key => $value)
                    <tr>
                        <td class="fw-semibold" style="width: 26%">{{ $key }}</td>
                        <td class="text-break">{{ $show($value) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($changes === [] && $context === [])
    <p class="text-muted mb-0">Nothing further was recorded for this row.</p>
@endif

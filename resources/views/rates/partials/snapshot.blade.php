{{--
    Today's rates, as read-only reference beside a form being filled in.

    Fetched by the "Today's Rates" button (components/todays-rates.blade.php) rather
    than rendered with the page, so opening an estimate never waits on a table nobody
    may look at, and reopening it after the morning entry shows the new figures.
--}}
@php($money = fn (?float $v, int $dp = 2) => $v === null ? '—' : number_format($v, $dp))

<div class="d-flex align-items-center justify-content-between mb-2">
    <div class="fw-semibold">{{ $date->format('d M Y') }}</div>

    @can('metal_rate.edit')
        <a href="{{ route('rates.today', ['date' => $date->toDateString()]) }}" class="btn btn-sm btn-soft-primary"
            target="_blank" rel="noopener">
            <i class="ri-external-link-line"></i> Enter rates
        </a>
    @endcan
</div>

@if ($rates->isEmpty())
    <div class="alert alert-warning mb-3 py-2">
        <i class="ri-error-warning-fill me-1"></i>
        No rates entered for {{ $date->format('d M Y') }}.
        @if ($lastEntered)
            The last day with rates was
            {{ \Illuminate\Support\Carbon::parse($lastEntered)->format('d M Y') }}.
        @endif
    </div>
@endif

@foreach ($purities as $metal => $group)
    <div class="mb-3">
        <div class="text-uppercase fw-bold fs-11 text-muted mb-1">{{ $metal }}</div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-centered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Purity</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Per</th>
                        <th class="text-end">Per Gram</th>
                        <th class="text-end">Per 10 g</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group as $purity)
                        @php($rate = $rates->get($purity->id))
                        <tr class="{{ $rate ? '' : 'text-muted' }}">
                            <td>{{ $purity->name }}</td>
                            <td class="text-end">{{ $money($rate ? (float) $rate->rate : null) }}</td>
                            <td class="text-end">
                                {{ $rate ? rtrim(rtrim(number_format((float) $rate->per_grams, 3, '.', ''), '0'), '.') . ' g' : '—' }}
                            </td>
                            <td class="text-end">{{ $money($rate ? (float) $rate->rate_per_gram : null, 4) }}</td>
                            {{-- What the estimate forms quote in, so it is spelt out
                                 rather than left as a sum to do in your head. --}}
                            <td class="text-end fw-semibold">
                                {{ $money($rate ? (float) $rate->rate_per_gram * 10 : null) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

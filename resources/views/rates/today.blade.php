@extends('layouts.app')

@section('title', "Today's Rates")

@section('content')
    <x-page-title :title="'Rates for ' . $date->format('d M Y')">
        <x-slot:actions>
            <a href="{{ route('rates.index') }}" class="btn btn-light">
                <i class="ri-history-line"></i> Rate History
            </a>
        </x-slot:actions>
    </x-page-title>

    <form method="POST" action="{{ route('rates.today.store') }}">
        @csrf

        <div class="row">
            <div class="col-lg-4 mb-3">
                <div class="card mb-0">
                    <div class="card-body">
                        <label for="date" class="form-label">Rate Date</label>
                        <div class="input-group">
                            <input type="date" id="date" name="date" class="form-control"
                                value="{{ $date->toDateString() }}" required>
                            <button type="button" class="btn btn-soft-secondary" id="load-date">
                                <i class="ri-search-line"></i> Load
                            </button>
                        </div>
                        <small class="text-muted">
                            Leave a box blank to keep whatever is already recorded for that purity.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        @forelse ($purities as $metalName => $group)
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">{{ $metalName }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20%">Purity</th>
                                    <th style="width: 25%">Rate (₹)</th>
                                    <th style="width: 20%">Per Grams</th>
                                    <th style="width: 20%">Works Out To</th>
                                    <th style="width: 15%">Current</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group as $purity)
                                    @php($current = $existing->get($purity->id))
                                    <tr>
                                        <td>
                                            <strong>{{ $purity->name }}</strong>
                                            @if ($purity->touch)
                                                <div class="text-muted fs-12">{{ rtrim(rtrim($purity->touch, '0'), '.') }}%</div>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0"
                                                class="form-control rate-input"
                                                name="rates[{{ $purity->id }}][rate]"
                                                value="{{ old("rates.{$purity->id}.rate", $current?->rate) }}"
                                                data-purity="{{ $purity->id }}" placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" min="0.001"
                                                class="form-control per-grams-input"
                                                name="rates[{{ $purity->id }}][per_grams]"
                                                value="{{ old("rates.{$purity->id}.per_grams", $current?->per_grams ?? $purity->default_per_grams) }}"
                                                data-purity="{{ $purity->id }}">
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary fs-13"
                                                id="per-gram-{{ $purity->id }}">—</span>
                                            <span class="text-muted fs-12">/ g</span>
                                        </td>
                                        <td>
                                            @if ($current)
                                                <span class="text-muted fs-13">₹{{ number_format((float) $current->rate_per_gram, 2) }}/g</span>
                                            @else
                                                <span class="text-muted fs-13">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-warning">
                No active purities found. Add metal types and purities first.
            </div>
        @endforelse

        @if ($purities->isNotEmpty())
            @can('metal_rate.edit')
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line"></i> Save Rates
                    </button>
                </div>
            @endcan
        @endif
    </form>
@endsection

@push('js')
    <script>
        $(function () {
            function refresh(purityId) {
                const rate = parseFloat($('.rate-input[data-purity="' + purityId + '"]').val());
                const per = parseFloat($('.per-grams-input[data-purity="' + purityId + '"]').val());
                const target = $('#per-gram-' + purityId);

                target.text(rate > 0 && per > 0 ? '₹' + (rate / per).toFixed(2) : '—');
            }

            $('.rate-input, .per-grams-input').on('input', function () {
                refresh($(this).data('purity'));
            }).each(function () {
                refresh($(this).data('purity'));
            });

            $('#load-date').on('click', function () {
                window.location = '{{ route('rates.today') }}?date=' + $('#date').val();
            });
        });
    </script>
@endpush

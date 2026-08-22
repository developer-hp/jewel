@extends('layouts.app')

@section('title', 'Daily Stock Report')

@php
    $wt = fn ($v) => number_format((float) $v, 3);
    $pcs = fn ($v) => number_format((int) $v);
@endphp

@section('content')
    <x-page-title title="Daily Stock Report">
        <x-slot:actions>
            <a href="{{ route('stock.daily.export', request()->only('date', 'metal_type_id')) }}" target="_blank"
                class="btn btn-success">
                <i class="ri-file-pdf-2-line"></i> Export
            </a>
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end mb-3">
                        <div class="col-md-2">
                            <label for="date" class="form-label mb-1 fs-13">Date</label>
                            <input type="date" id="date" name="date" class="form-control"
                                value="{{ $date->toDateString() }}" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label for="metal_type_id" class="form-label mb-1 fs-13">Metal Type</label>
                            <select id="metal_type_id" name="metal_type_id" class="form-select"
                                onchange="this.form.submit()">
                                <option value="">All metal types</option>
                                @foreach ($metalTypes as $id => $name)
                                    <option value="{{ $id }}" @selected($metalTypeId == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <a href="{{ route('stock.daily') }}" class="btn btn-danger">
                                <i class="ri-refresh-line"></i> Today
                            </a>
                        </div>
                    </form>

                    <p class="text-muted fs-13">
                        Opening is everything that was already here; Add came in on
                        {{ $date->format('d-m-Y') }}, Less went out. Closing is the three of them
                        together. Sold will split out of Less once sales are recorded.
                    </p>

                    @php($hidden = $allGroups->where('show_in_daily_report', false))

                    <div class="mb-3">
                        <a class="btn btn-sm btn-soft-secondary" data-bs-toggle="collapse" href="#group-picker"
                            role="button" aria-expanded="false" aria-controls="group-picker">
                            <i class="ri-list-check-2"></i> Item groups shown
                            @if ($hidden->isNotEmpty())
                                <span class="badge bg-warning text-dark ms-1">{{ $hidden->count() }} hidden</span>
                            @endif
                        </a>

                        <div class="collapse mt-2 {{ $hidden->isNotEmpty() ? 'show' : '' }}" id="group-picker">
                            <div class="card border mb-0">
                                <div class="card-body">
                                    {{-- Saved on the groups themselves, so this holds for
                                         everyone and every day until it is changed again. --}}
                                    <p class="text-muted fs-13">
                                        Untick a group to leave it off this report. The choice is shared
                                        by everyone and stays until it is changed here.
                                    </p>

                                    <form method="POST" action="{{ route('stock.daily.groups') }}">
                                        @csrf
                                        {{-- Present even when every box is unticked, so the server
                                             is told "none" rather than nothing at all. --}}
                                        <input type="hidden" name="item_group_ids[]" value="">

                                        <div class="row row-cols-2 row-cols-md-4 g-2 mb-3">
                                            @foreach ($allGroups as $group)
                                                <div class="col">
                                                    <div class="form-check">
                                                        <input class="form-check-input group-pick" type="checkbox"
                                                            name="item_group_ids[]" value="{{ $group->id }}"
                                                            id="group-{{ $group->id }}"
                                                            @checked($group->show_in_daily_report)>
                                                        <label class="form-check-label" for="group-{{ $group->id }}">
                                                            <code>{{ $group->prefix }}</code> {{ $group->name }}
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="ri-save-line"></i> Save
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light" id="group-all">
                                                Select all
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light" id="group-none">
                                                Select none
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-centered table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" class="align-middle">Code</th>
                                    <th rowspan="2" class="align-middle">Group</th>
                                    <th colspan="2" class="text-center">Opening</th>
                                    <th colspan="2" class="text-center">Add</th>
                                    <th colspan="2" class="text-center">Less</th>
                                    <th colspan="2" class="text-center">Closing</th>
                                </tr>
                                <tr>
                                    @foreach (['Opening', 'Add', 'Less', 'Closing'] as $heading)
                                        <th class="text-end">Pcs</th>
                                        <th class="text-end">Wt</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td><code>{{ $row->code }}</code></td>
                                        <td>{{ $row->name }}</td>
                                        <td class="text-end">{{ $pcs($row->opening_pcs) }}</td>
                                        <td class="text-end">{{ $wt($row->opening_wt) }}</td>
                                        <td class="text-end">{{ $pcs($row->add_pcs) }}</td>
                                        <td class="text-end">{{ $wt($row->add_wt) }}</td>
                                        <td class="text-end">{{ $pcs($row->less_pcs) }}</td>
                                        <td class="text-end">{{ $wt($row->less_wt) }}</td>
                                        <td class="text-end fw-semibold">{{ $pcs($row->closing_pcs) }}</td>
                                        <td class="text-end fw-semibold">{{ $wt($row->closing_wt) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="2" class="text-end">Total</td>
                                    <td class="text-end">{{ $pcs($totals->opening_pcs) }}</td>
                                    <td class="text-end">{{ $wt($totals->opening_wt) }}</td>
                                    <td class="text-end">{{ $pcs($totals->add_pcs) }}</td>
                                    <td class="text-end">{{ $wt($totals->add_wt) }}</td>
                                    <td class="text-end">{{ $pcs($totals->less_pcs) }}</td>
                                    <td class="text-end">{{ $wt($totals->less_wt) }}</td>
                                    <td class="text-end">{{ $pcs($totals->closing_pcs) }}</td>
                                    <td class="text-end">{{ $wt($totals->closing_wt) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(function () {
            $('#group-all').on('click', () => $('.group-pick').prop('checked', true));
            $('#group-none').on('click', () => $('.group-pick').prop('checked', false));
        });
    </script>
@endpush

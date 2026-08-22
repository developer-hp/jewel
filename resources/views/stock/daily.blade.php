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

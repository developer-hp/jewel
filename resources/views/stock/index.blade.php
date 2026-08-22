@extends('layouts.app')

@section('title', 'Stock Summary')

@php
    $wt = fn ($v) => number_format((float) $v, 3);
    $pcs = fn ($v) => number_format((int) $v);
@endphp

@section('content')
    <x-page-title title="Stock Summary">
        <x-slot:actions>
            <a href="{{ route('stock.print', request()->only('metal_type_id')) }}" target="_blank"
                class="btn btn-secondary">
                <i class="ri-printer-line"></i> Print
            </a>
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end mb-0">
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
                        @if ($metalTypeId)
                            <div class="col-auto">
                                <a href="{{ route('stock.index') }}" class="btn btn-danger">
                                    <i class="ri-refresh-line"></i> Reset
                                </a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach ([
            ['title' => 'By Item Group', 'label' => 'Group', 'rows' => $itemGroups, 'totals' => $itemGroupTotals],
            ['title' => 'By Stock Group', 'label' => 'Stock Group', 'rows' => $stockGroups, 'totals' => $stockGroupTotals],
        ] as $table)
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">{{ $table['title'] }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-centered table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>{{ $table['label'] }}</th>
                                        <th class="text-end">Pcs</th>
                                        <th class="text-end">Held</th>
                                        <th class="text-end">Gross Wt</th>
                                        <th class="text-end">Net Wt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($table['rows'] as $row)
                                        <tr>
                                            <td><code>{{ $row->code }}</code></td>
                                            <td>{{ $row->name }}</td>
                                            <td class="text-end">{{ $pcs($row->pcs) }}</td>
                                            <td class="text-end">
                                                @if ($row->held > 0)
                                                    {{-- Promised to a customer, but still on the shelf. --}}
                                                    <span class="badge bg-warning text-dark">{{ $pcs($row->held) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $wt($row->gross) }}</td>
                                            <td class="text-end">{{ $wt($row->net) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-muted text-center">Nothing to show.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="2" class="text-end">Total</td>
                                        <td class="text-end">{{ $pcs($table['totals']->pcs) }}</td>
                                        <td class="text-end">{{ $pcs($table['totals']->held) }}</td>
                                        <td class="text-end">{{ $wt($table['totals']->gross) }}</td>
                                        <td class="text-end">{{ $wt($table['totals']->net) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

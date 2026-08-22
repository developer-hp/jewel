@extends('layouts.app')

@section('title', 'Internal Stock')

@include('layouts.partials.datatables-assets')

@php
    $weight = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    // Cycled rather than stored: a pot is not worth a colour column.
    $palette = ['bg-info', 'bg-primary', 'bg-warning', 'bg-secondary', 'bg-success', 'bg-danger'];
@endphp

@section('content')
    <x-page-title title="Internal Stock">
        <x-slot:actions>
            @can('internal_stock_entry.print')
                <a href="{{ route('internal-stock-entries.export') }}" target="_blank" class="btn btn-success" id="export-link">
                    <i class="ri-file-pdf-2-line"></i> Export
                </a>
            @endcan
            @can('internal_stock_entry.create')
                <a href="{{ route('internal-stock-entries.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    @if ($stocks->isNotEmpty())
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-3">
            @foreach ($stocks as $stock)
                <div class="col">
                    <div class="card {{ $palette[$loop->index % count($palette)] }} h-100">
                        <div class="card-body">
                            <h6 class="text-white text-uppercase mb-2">
                                <i class="ri-arrow-right-s-line"></i> {{ $stock->name }}
                            </h6>
                            <h3 class="text-white mb-0">{{ $weight($stock->balance()) }} GM</h3>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <label for="filter-stock" class="form-label mb-1 fs-13">Internal Stock</label>
                            <select id="filter-stock" class="form-select">
                                <option value="">All</option>
                                @foreach ($stocks as $stock)
                                    <option value="{{ $stock->id }}">{{ $stock->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="filter-type" class="form-label mb-1 fs-13">Type</label>
                            <select id="filter-type" class="form-select">
                                <option value="">All</option>
                                @foreach (App\Models\InternalStockEntry::TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-auto">
                            <button type="button" id="filter-reset" class="btn btn-danger">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>
                    </div>

                    <table id="stock-entries-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Internal Stock</th>
                                <th class="text-end">Gold In</th>
                                <th class="text-end">Gold Out</th>
                                <th>Note</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                    </table>

                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(function () {
            const table = window.appDataTable('#stock-entries-table', {
                ajax: {
                    url: '{{ route('internal-stock-entries.index') }}',
                    data: function (params) {
                        params.internal_stock_id = $('#filter-stock').val();
                        params.type = $('#filter-type').val();
                    }
                },
                order: [[1, 'asc']],
                columns: [
                    { data: 'type_label', name: 'type_label', searchable: false },
                    { data: 'stock', name: 'stock' },
                    { data: 'gold_in', name: 'gold_in', searchable: false, className: 'text-end' },
                    { data: 'gold_out', name: 'gold_out', searchable: false, className: 'text-end' },
                    { data: 'note', name: 'note' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            // What prints is what is on screen, so the export carries the filters.
            function refreshExport() {
                const params = $.param({
                    internal_stock_id: $('#filter-stock').val() || '',
                    type: $('#filter-type').val() || ''
                });

                $('#export-link').attr('href', '{{ route('internal-stock-entries.export') }}?' + params);
            }

            $('#filter-stock, #filter-type').on('change', function () {
                refreshExport();
                table.ajax.reload();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-stock, #filter-type').val('');
                refreshExport();
                table.search('').ajax.reload();
            });

            refreshExport();
        });
    </script>
@endpush

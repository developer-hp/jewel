@extends('layouts.app')

@section('title', 'Item Lots')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Item Lots">
        <x-slot:actions>
            @can('item_lot.create')
                <a href="{{ route('lots.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> New Lot
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 mb-3">
                        <div class="col-md-2">
                            <select id="filter-status" class="form-select">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filter-supplier" class="form-select">
                                <option value="">All suppliers</option>
                                @foreach ($suppliers as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="filter-reset" class="btn btn-danger">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>
                    </div>

                    <table id="lots-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Lot</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Groups (pcs/tags)</th>
                                <th style="width: 15%">Progress</th>
                                <th>Gross</th>
                                <th>Status</th>
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
            const table = window.appDataTable('#lots-table', {
                ajax: {
                    url: '{{ route('lots.index') }}',
                    data: function (params) {
                        params.status = $('#filter-status').val();
                        params.supplier_id = $('#filter-supplier').val();
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'code', name: 'code' },
                    { data: 'lot_date', name: 'lot_date', searchable: false },
                    { data: 'supplier', name: 'supplier' },
                    { data: 'groups', name: 'groups', orderable: false, searchable: false },
                    { data: 'progress', name: 'progress', orderable: false, searchable: false },
                    { data: 'weight', name: 'weight', orderable: false, searchable: false },
                    { data: 'status_badge', name: 'status_badge', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-status, #filter-supplier').on('change', () => table.ajax.reload());

            $('#filter-reset').on('click', function () {
                $('#filter-status, #filter-supplier').val('');
                table.search('').ajax.reload();
            });
        });
    </script>
@endpush

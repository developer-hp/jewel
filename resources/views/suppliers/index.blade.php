@extends('layouts.app')

@section('title', 'Suppliers')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Suppliers">
        <x-slot:actions>
            @can('supplier.create')
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Supplier
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select id="filter-city" class="form-select">
                                <option value="">All cities</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}">{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="filter-status" class="form-select">
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="filter-reset" class="btn btn-danger">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>
                    </div>

                    <table id="suppliers-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Short Name</th>
                                <th>City</th>
                                <th>Contact</th>
                                <th>Items</th>
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
            const table = window.appDataTable('#suppliers-table', {
                ajax: {
                    url: '{{ route('suppliers.index') }}',
                    data: function (params) {
                        params.city = $('#filter-city').val();
                        params.status = $('#filter-status').val();
                    }
                },
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'short_name', name: 'short_name' },
                    { data: 'city', name: 'city' },
                    { data: 'contact', name: 'contact' },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-city, #filter-status').on('change', () => table.ajax.reload());

            $('#filter-reset').on('click', function () {
                $('#filter-city, #filter-status').val('');
                table.search('').ajax.reload();
            });
        });
    </script>
@endpush

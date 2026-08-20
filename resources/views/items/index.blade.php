@extends('layouts.app')

@section('title', 'Items')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Items">
        <x-slot:actions>
            @can('item.edit')
                <a href="{{ route('items.photos.bulk') }}" class="btn btn-soft-primary">
                    <i class="ri-image-add-line"></i> Bulk Photos
                </a>
            @endcan
            @can('item.create')
                <a href="{{ route('items.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Item
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
                            <select id="filter-group" class="form-select">
                                <option value="">All groups</option>
                                @foreach ($groups as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filter-metal" class="form-select">
                                <option value="">All metal types</option>
                                @foreach ($metalTypes as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
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
                            <select id="filter-status" class="form-select">
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="filter-reset" class="btn btn-light w-100">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>
                    </div>

                    <table id="items-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 48px;"></th>
                                <th>Code</th>
                                <th>HUID</th>
                                <th>Name</th>
                                <th>Group</th>
                                <th>Supplier</th>
                                <th>Metal / Purity</th>
                                <th>Weight</th>
                                <th>Making</th>
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
            const table = window.appDataTable('#items-table', {
                ajax: {
                    url: '{{ route('items.index') }}',
                    data: function (params) {
                        params.item_group_id = $('#filter-group').val();
                        params.supplier_id = $('#filter-supplier').val();
                        params.metal_type_id = $('#filter-metal').val();
                        params.status = $('#filter-status').val();
                    }
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'photo', name: 'photo', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'code', name: 'code' },
                    { data: 'huid', name: 'huid' },
                    { data: 'name', name: 'name' },
                    { data: 'group', name: 'group' },
                    { data: 'supplier', name: 'supplier', orderable: false },
                    { data: 'metal', name: 'metal', orderable: false, searchable: false },
                    { data: 'weights', name: 'weights', searchable: false },
                    { data: 'making', name: 'making', orderable: false, searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-group, #filter-metal, #filter-supplier, #filter-status').on('change', () => table.ajax.reload());

            $('#filter-reset').on('click', function () {
                $('#filter-group, #filter-metal, #filter-supplier, #filter-status').val('');
                table.search('').ajax.reload();
            });
        });
    </script>
@endpush

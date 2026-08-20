@extends('layouts.app')

@section('title', 'Item Groups')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Item Groups">
        <x-slot:actions>
            @can('item_group.create')
                <a href="{{ route('item-groups.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Item Group
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Each group owns a code prefix and its own running number, so item codes read
                        <code>RNG0001</code>, <code>NCK0001</code> and so on.
                    </p>

                    <table id="item-groups-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Prefix</th>
                                <th>Next Code</th>
                                <th>Metal Type</th>
                                <th>Stock Group</th>
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
            window.appDataTable('#item-groups-table', {
                ajax: '{{ route('item-groups.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'prefix', name: 'prefix' },
                    { data: 'next_code', name: 'next_code', orderable: false, searchable: false },
                    { data: 'metal_type', name: 'metal_type', searchable: false },
                    { data: 'stock_group', name: 'stock_group' },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

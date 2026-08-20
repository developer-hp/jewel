@extends('layouts.app')

@section('title', 'Stock Groups')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Stock Groups">
        <x-slot:actions>
            @can('stock_group.create')
                <a href="{{ route('stock-groups.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Stock Group
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Item groups roll up into a stock group, so holdings can be reported by it.
                    </p>

                    <table id="stock-groups-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Item Groups</th>
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
            window.appDataTable('#stock-groups-table', {
                ajax: '{{ route('stock-groups.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'code', name: 'code' },
                    { data: 'item_groups_count', name: 'item_groups_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

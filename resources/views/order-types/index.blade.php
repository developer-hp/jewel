@extends('layouts.app')

@section('title', 'Order Types')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Order Types">
        <x-slot:actions>
            @can('order_type.create')
                <a href="{{ route('order-types.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Order Type
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        What kind of work goes out to a karigar — CZ, Stock, and whatever else you
                        need. The name is copied onto the order when it is saved and prints in the
                        receipt's ITEM CODE row, so renaming one here never alters a slip that has
                        already printed.
                    </p>

                    <table id="order-types-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Orders</th>
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
            window.appDataTable('#order-types-table', {
                ajax: '{{ route('order-types.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'supplier_orders_count', name: 'supplier_orders_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

@extends('layouts.app')

@section('title', 'Customers')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Customers">
        <x-slot:actions>
            @can('customer.create')
                <a href="{{ route('customers.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Customer
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        The phone number identifies a customer. Taking a repair in adds anyone
                        new automatically, and fills the name and address back in the next time
                        the same number comes to the counter.
                    </p>

                    <table id="customers-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Repairs</th>
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
            window.appDataTable('#customers-table', {
                ajax: '{{ route('customers.index') }}',
                order: [[0, 'asc']],
                searchPlaceholder: 'Search name or number…',
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'phone', name: 'phone' },
                    { data: 'address', name: 'address' },
                    { data: 'repair_forms_count', name: 'repair_forms_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

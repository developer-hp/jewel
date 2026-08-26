@extends('layouts.app')

@section('title', 'Cash Drawers')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Cash Drawers">
        <x-slot:actions>
            @can('cash_drawer.create')
                <a href="{{ route('cash-drawers.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Drawer
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Balance is the opening figure plus every entry booked since — money in
                        adds, money out takes away.
                    </p>

                    <table id="cash-drawers-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th class="text-end">Opening</th>
                                <th class="text-end">Balance</th>
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
            window.appDataTable('#cash-drawers-table', {
                ajax: '{{ route('cash-drawers.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'opening_balance', name: 'opening_balance', searchable: false, className: 'text-end' },
                    { data: 'balance', name: 'balance', searchable: false, className: 'text-end' },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

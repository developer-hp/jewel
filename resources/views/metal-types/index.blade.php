@extends('layouts.app')

@section('title', 'Metal Types')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Metal Types">
        <x-slot:actions>
            @can('metal_type.create')
                <a href="{{ route('metal-types.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Metal Type
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="metal-types-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Purities</th>
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
            window.appDataTable('#metal-types-table', {
                ajax: '{{ route('metal-types.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'code', name: 'code' },
                    { data: 'purities_count', name: 'purities_count', searchable: false },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

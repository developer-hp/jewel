@extends('layouts.app')

@section('title', 'Purities')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Purities">
        <x-slot:actions>
            @can('metal_rate.view')
                <a href="{{ route('rates.today') }}" class="btn btn-soft-primary">
                    <i class="ri-calendar-check-line"></i> Today's Rates
                </a>
            @endcan
            @can('purity.create')
                <a href="{{ route('purities.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Purity
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
                            <select id="filter-metal" class="form-select">
                                <option value="">All metal types</option>
                                @foreach ($metalTypes as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <table id="purities-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Metal Type</th>
                                <th>Purity</th>
                                <th>Touch %</th>
                                <th>Current Rate</th>
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
            const table = window.appDataTable('#purities-table', {
                ajax: {
                    url: '{{ route('purities.index') }}',
                    data: function (params) {
                        params.metal_type_id = $('#filter-metal').val();
                    }
                },
                order: [[0, 'asc']],
                columns: [
                    { data: 'metal_type', name: 'metal_type' },
                    { data: 'name', name: 'name' },
                    { data: 'touch', name: 'touch' },
                    { data: 'rate', name: 'rate', orderable: false, searchable: false },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-metal').on('change', () => table.ajax.reload());
        });
    </script>
@endpush

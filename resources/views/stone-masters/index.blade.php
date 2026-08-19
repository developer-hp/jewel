@extends('layouts.app')

@section('title', $plural)

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title :title="$plural">
        <x-slot:actions>
            @can('stone.create')
                <a href="{{ route($routePrefix . '.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add {{ $singular }}
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
                            <select id="filter-unit" class="form-select">
                                <option value="">All rate units</option>
                                @foreach ($rateUnits as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <table id="stones-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Attributes</th>
                                <th>Rate</th>
                                <th>Used On</th>
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
            const table = window.appDataTable('#stones-table', {
                ajax: {
                    url: '{{ route($routePrefix . '.index') }}',
                    data: function (params) {
                        params.rate_unit = $('#filter-unit').val();
                    }
                },
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'code', name: 'code' },
                    { data: 'attributes', name: 'attributes', orderable: false, searchable: false },
                    { data: 'rate', name: 'rate', searchable: false },
                    { data: 'item_stones_count', name: 'item_stones_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-unit').on('change', () => table.ajax.reload());
        });
    </script>
@endpush

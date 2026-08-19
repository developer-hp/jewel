@extends('layouts.app')

@section('title', 'Making Charges')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Making Charges">
        <x-slot:actions>
            @can('making_charge.create')
                <a href="{{ route('making-charges.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Making Charge
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Items carry a making-charge code; the charge itself is applied when the item is quoted.
                    </p>

                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select id="filter-type" class="form-select">
                                <option value="">All charge types</option>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <table id="making-charges-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Applies As</th>
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
            const table = window.appDataTable('#making-charges-table', {
                ajax: {
                    url: '{{ route('making-charges.index') }}',
                    data: function (params) {
                        params.charge_type = $('#filter-type').val();
                    }
                },
                order: [[0, 'asc']],
                columns: [
                    { data: 'code', name: 'code' },
                    { data: 'name', name: 'name' },
                    { data: 'type', name: 'type', searchable: false },
                    { data: 'applies', name: 'applies', searchable: false },
                    { data: 'items_count', name: 'items_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-type').on('change', () => table.ajax.reload());
        });
    </script>
@endpush

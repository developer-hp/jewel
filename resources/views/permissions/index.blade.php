@extends('layouts.app')

@section('title', 'Permissions')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Permissions">
        <x-slot:actions>
            @can('permission.create')
                <a href="{{ route('permissions.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Permission
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
                            <select id="filter-module" class="form-select">
                                <option value="">All modules</option>
                                @foreach ($modules as $module)
                                    <option value="{{ $module }}" class="text-capitalize">{{ ucfirst($module) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <table id="permissions-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Permission</th>
                                <th>Module</th>
                                <th>Roles</th>
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
            const table = window.appDataTable('#permissions-table', {
                ajax: {
                    url: '{{ route('permissions.index') }}',
                    data: function (params) {
                        params.module = $('#filter-module').val();
                    }
                },
                pageLength: 25,
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'module', name: 'module', searchable: false },
                    { data: 'roles_count', name: 'roles_count', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-module').on('change', () => table.ajax.reload());
        });
    </script>
@endpush

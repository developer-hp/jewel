@extends('layouts.app')

@section('title', 'Roles')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Roles">
        <x-slot:actions>
            @can('role.create')
                <a href="{{ route('roles.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Role
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <table id="roles-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Role</th>
                                <th>Permissions</th>
                                <th>Users</th>
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
            window.appDataTable('#roles-table', {
                ajax: '{{ route('roles.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'permissions', name: 'permissions', searchable: false },
                    { data: 'users_count', name: 'users_count', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

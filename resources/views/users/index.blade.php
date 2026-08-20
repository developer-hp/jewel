@extends('layouts.app')

@section('title', 'Users')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Users">
        <x-slot:actions>
            @can('user.create')
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add User
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
                            <select id="filter-role" class="form-select">
                                <option value="">All roles</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filter-status" class="form-select">
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="filter-reset" class="btn btn-danger">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>
                    </div>

                    <table id="users-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Contact</th>
                                <th>Roles</th>
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
            const table = window.appDataTable('#users-table', {
                ajax: {
                    url: '{{ route('users.index') }}',
                    data: function (params) {
                        params.role = $('#filter-role').val();
                        params.status = $('#filter-status').val();
                    }
                },
                order: [[1, 'asc']],
                columns: [
                    { data: 'user', name: 'user' },
                    { data: 'username', name: 'username' },
                    { data: 'contact', name: 'contact' },
                    { data: 'roles', name: 'roles', orderable: false, searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-role, #filter-status').on('change', () => table.ajax.reload());

            $('#filter-reset').on('click', function () {
                $('#filter-role, #filter-status').val('');
                table.search('').ajax.reload();
            });
        });
    </script>
@endpush

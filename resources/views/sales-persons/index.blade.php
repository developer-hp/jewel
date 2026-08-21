@extends('layouts.app')

@section('title', 'Sales Persons')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Sales Persons">
        <x-slot:actions>
            @can('sales_person.create')
                <a href="{{ route('sales-persons.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Sales Person
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Counter staff a repair form can be booked against. The name is copied onto
                        the form when it is saved, so renaming someone here never alters a form
                        that has already printed.
                    </p>

                    <table id="sales-persons-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>City</th>
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
            window.appDataTable('#sales-persons-table', {
                ajax: '{{ route('sales-persons.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'phone', name: 'phone' },
                    { data: 'city', name: 'city' },
                    { data: 'repair_form_links_count', name: 'repair_form_links_count', searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

@extends('layouts.app')

@section('title', 'Sold Items')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Sold Items" />

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Pieces quoted on an estimate that a cash entry has settled — the money
                        is in, so these are on their way out of stock. Marking one sold dates it
                        today and takes it off the stock figures; marking it available puts it
                        back.
                    </p>

                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select id="filter-state" class="form-select">
                                <option value="">All</option>
                                <option value="in_stock" selected>Still in stock</option>
                                <option value="sold">Sold</option>
                            </select>
                        </div>
                    </div>

                    <table id="sold-items-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Group</th>
                                <th>Metal</th>
                                <th class="text-end">Net Wt</th>
                                <th>Settled By</th>
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
            const table = window.appDataTable('#sold-items-table', {
                ajax: {
                    url: '{{ route('sold-items.index') }}',
                    data: d => { d.state = $('#filter-state').val(); },
                },
                order: [[0, 'asc']],
                columns: [
                    { data: 'code', name: 'code' },
                    { data: 'group', name: 'itemGroup.name' },
                    { data: 'metal', name: 'metalType.name' },
                    { data: 'net_weight', name: 'net_weight', searchable: false, className: 'text-end' },
                    { data: 'settled', name: 'settled', orderable: false, searchable: false },
                    { data: 'sold', name: 'sold', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-state').on('change', () => table.ajax.reload());
        });
    </script>
@endpush

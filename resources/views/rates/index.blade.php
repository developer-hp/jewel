@extends('layouts.app')

@section('title', 'Rate History')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Rate History">
        <x-slot:actions>
            @can('metal_rate.edit')
                <a href="{{ route('rates.today') }}" class="btn btn-primary">
                    <i class="ri-calendar-check-line"></i> Enter Today's Rates
                </a>
            @endcan
            @can('metal_rate.create')
                <a href="{{ route('rates.create') }}" class="btn btn-soft-primary">
                    <i class="ri-add-line"></i> Single Rate
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
                            <select id="filter-purity" class="form-select">
                                <option value="">All purities</option>
                                @foreach ($purities as $purity)
                                    <option value="{{ $purity->id }}">{{ $purity->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="filter-from" class="form-control" placeholder="From">
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="filter-to" class="form-control" placeholder="To">
                        </div>
                        <div class="col-md-2">
                            <button type="button" id="filter-reset" class="btn btn-light w-100">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>
                    </div>

                    <table id="rates-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Purity</th>
                                <th>Date</th>
                                <th>Entered As</th>
                                <th>Per Gram (₹)</th>
                                <th>By</th>
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
            const table = window.appDataTable('#rates-table', {
                ajax: {
                    url: '{{ route('rates.index') }}',
                    data: function (params) {
                        params.purity_id = $('#filter-purity').val();
                        params.from = $('#filter-from').val();
                        params.to = $('#filter-to').val();
                    }
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'purity', name: 'purity' },
                    { data: 'effective_date', name: 'effective_date', searchable: false },
                    { data: 'entered', name: 'entered', orderable: false, searchable: false },
                    { data: 'rate_per_gram', name: 'rate_per_gram', searchable: false },
                    { data: 'by', name: 'by', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });

            $('#filter-purity, #filter-from, #filter-to').on('change', () => table.ajax.reload());

            $('#filter-reset').on('click', function () {
                $('#filter-purity, #filter-from, #filter-to').val('');
                table.search('').ajax.reload();
            });
        });
    </script>
@endpush

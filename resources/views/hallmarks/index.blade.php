@extends('layouts.app')

@section('title', 'Hallmark')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Hallmark">
        <x-slot:actions>
            @can('hallmark.create')
                <a href="{{ route('hallmarks.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> New Hallmark
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="hallmarks-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Lot No</th>
                                <th>Date</th>
                                <th>Lines</th>
                                <th class="text-end">Total Pcs</th>
                                <th class="text-end">Gross Wt</th>
                                <th class="text-end">Total Cost</th>
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
            window.appDataTable('#hallmarks-table', {
                ajax: '{{ route('hallmarks.index') }}',
                order: [[0, 'desc']],
                columns: [
                    { data: 'lot_no', name: 'lot_no' },
                    { data: 'hallmark_date', name: 'hallmark_date', searchable: false },
                    { data: 'lines_count', name: 'lines_count', searchable: false },
                    { data: 'pieces', name: 'pieces', searchable: false, className: 'text-end' },
                    { data: 'gross', name: 'gross_weight', searchable: false, className: 'text-end' },
                    { data: 'cost', name: 'cost', orderable: false, searchable: false, className: 'text-end' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

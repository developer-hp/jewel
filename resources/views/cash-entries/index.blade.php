@extends('layouts.app')

@section('title', 'Cash Entries')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Cash">
        <x-slot:actions>
            <a href="{{ route('cash-entries.export') }}" class="btn btn-success" target="_blank">
                <i class="ri-file-download-line"></i> Export
            </a>
            @can('cash_entry.create')
                <a href="{{ route('cash-entries.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Entry
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    {{-- What should be in the tills right now, and the gold that came over the
         counter to get there. Both signed: money and metal paid back out count
         against what came in. --}}
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-md d-flex align-items-center justify-content-center rounded-circle bg-warning-subtle">
                        <i class="ri-cash-fill fs-24 text-warning"></i>
                    </span>
                    <div class="text-end ms-auto">
                        <h3 class="mb-0">{{ number_format($position->cash, 0) }} <span class="fs-14">INR</span></h3>
                        <p class="text-muted mb-0 fs-12 text-uppercase">Total Cash</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-md d-flex align-items-center justify-content-center rounded-circle bg-success-subtle">
                        <i class="ri-copper-diamond-fill fs-24 text-success"></i>
                    </span>
                    <div class="text-end ms-auto">
                        <h3 class="mb-0 text-success">
                            {{ rtrim(rtrim(number_format($position->gold, 3, '.', ''), '0'), '.') ?: '0' }}
                            <span class="fs-14">grm</span>
                        </h3>
                        <p class="text-muted mb-0 fs-12 text-uppercase">Total Gold</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="cash-entries-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Ref No</th>
                                <th>Date</th>
                                <th>Drawer</th>
                                <th>Document</th>
                                <th>Event</th>
                                <th class="text-end">Final</th>
                                <th class="text-end">Cash</th>
                                <th class="text-end">Cheque</th>
                                <th class="text-end">Gold</th>
                                <th class="text-end">Discount</th>
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
            window.appDataTable('#cash-entries-table', {
                ajax: '{{ route('cash-entries.index') }}',
                order: [[0, 'desc']],
                columns: [
                    { data: 'ref', name: 'ref' },
                    { data: 'entry_date', name: 'entry_date' },
                    { data: 'drawer_name', name: 'drawer.name' },
                    { data: 'document', name: 'document' },
                    { data: 'event', name: 'cash_event', searchable: false },
                    { data: 'final_amount', name: 'final_amount', searchable: false, className: 'text-end' },
                    { data: 'cash_amount', name: 'cash_amount', searchable: false, className: 'text-end' },
                    { data: 'cheque_amount', name: 'cheque_amount', searchable: false, className: 'text-end' },
                    { data: 'gold_amount', name: 'gold_amount', searchable: false, className: 'text-end' },
                    { data: 'discount', name: 'discount', orderable: false, searchable: false, className: 'text-end' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

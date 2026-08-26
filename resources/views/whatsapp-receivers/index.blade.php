@extends('layouts.app')

@section('title', 'Report Receivers')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Report Receivers">
        <x-slot:actions>
            @can('app_setting.edit')
                <a href="{{ route('whatsapp-receivers.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Receiver
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Everyone here gets the day opening's three reports on WhatsApp. A number
                        WhatsApp cannot reach is flagged below rather than failing quietly during
                        an opening.
                    </p>

                    <table id="receivers-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Sends as</th>
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
            window.appDataTable('#receivers-table', {
                ajax: '{{ route('whatsapp-receivers.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'mobile', name: 'mobile' },
                    { data: 'sendable', name: 'sendable', orderable: false, searchable: false },
                    { data: 'status', name: 'status', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

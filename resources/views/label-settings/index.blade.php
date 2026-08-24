@extends('layouts.app')

@section('title', 'Label Templates')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Label Templates">
        <x-slot:actions>
            @can('label_setting.create')
                <a href="{{ route('label-settings.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Template
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info py-2">
                <i class="ri-information-fill me-1"></i>
                A piece prints with the template chosen on its <strong>metal type</strong>
                (Masters &rsaquo; Metal Types). Anything with no template of its own uses the
                one marked <span class="badge bg-success">Default</span>.
            </div>

            <div class="card">
                <div class="card-body">
                    <table id="label-settings-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Layout</th>
                                <th>Size</th>
                                <th>Font</th>
                                <th>QR</th>
                                <th>Metal Types</th>
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
            window.appDataTable('#label-settings-table', {
                ajax: '{{ route('label-settings.index') }}',
                order: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'layout', name: 'layout' },
                    { data: 'size', name: 'size', searchable: false },
                    { data: 'font_size_pt', name: 'font_size_pt', searchable: false },
                    { data: 'qr', name: 'qr', searchable: false },
                    { data: 'metal_types_count', name: 'metal_types_count', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

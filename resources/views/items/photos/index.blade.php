@extends('layouts.app')

@section('title', 'Item Photos')

@include('layouts.partials.datatables-assets')

@section('content')
    <x-page-title title="Item Photos">
        <x-slot:actions>
            @can('item.edit')
                <a href="{{ route('items.photos.bulk') }}" class="btn btn-primary">
                    <i class="ri-upload-2-line"></i> Bulk Upload
                </a>
            @endcan
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        {{ number_format($total) }} {{ Str::plural('piece', $total) }} with a photo.
                        Only the code is listed — a page of thumbnails is a page of downloads.
                        Open one to see it at full size.
                    </p>

                    <table id="item-photos-table" class="table table-centered table-hover dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Group</th>
                                <th>Metal / Purity</th>
                                <th class="text-end">Photo</th>
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
            window.appDataTable('#item-photos-table', {
                ajax: '{{ route('items.photos.index') }}',
                order: [[0, 'desc']],
                searchPlaceholder: 'Search code, HUID or name…',
                columns: [
                    { data: 'code', name: 'code' },
                    { data: 'name', name: 'name' },
                    { data: 'group', name: 'group' },
                    { data: 'metal', name: 'metal', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ]
            });
        });
    </script>
@endpush

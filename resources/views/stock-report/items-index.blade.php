@extends('layouts.app')

@section('title', 'Stock Items Report')

@section('content')
    <x-page-title title="Stock Items Report">
        <x-slot:description>
            Filter and export stock items with details.
        </x-slot:description>
    </x-page-title>

    <div class="card">
        <div class="card-body">
            <!-- Filter Form -->
            <form id="filterForm" class="row g-3 mb-4" action="#" method="GET">
                <div class="col-md-3">
                    <label for="item_group_id" class="form-label">Item Group</label>
                    <select name="item_group_id" id="item_group_id" class="form-select form-select-sm">
                        <option value="">All Item Groups</option>
                        @foreach ($itemGroups as $group)
                            <option value="{{ $group->id }}" {{ request('item_group_id') == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="stock_group_id" class="form-label">Stock Group</label>
                    <select name="stock_group_id" id="stock_group_id" class="form-select form-select-sm">
                        <option value="">All Stock Groups</option>
                        @foreach ($stockGroups as $group)
                            <option value="{{ $group->id }}" {{ request('stock_group_id') == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="metal_type_id" class="form-label">Metal Type</label>
                    <select name="metal_type_id" id="metal_type_id" class="form-select form-select-sm">
                        <option value="">All Metal Types</option>
                        @foreach ($metalTypes as $type)
                            <option value="{{ $type->id }}" {{ request('metal_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="purity_id" class="form-label">Purity</label>
                    <select name="purity_id" id="purity_id" class="form-select form-select-sm">
                        <option value="">All Purities</option>
                        @foreach ($purities as $purity)
                            <option value="{{ $purity->id }}" {{ request('purity_id') == $purity->id ? 'selected' : '' }}>
                                {{ $purity->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">All Items</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="sold" {{ request('status') == 'sold' ? 'selected' : '' }}>Sold Only</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('stock-items.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                    <form action="{{ route('stock-items.export') }}" method="POST" style="display: inline;">
                        @csrf
                        @foreach (['item_group_id', 'stock_group_id', 'metal_type_id', 'purity_id', 'status'] as $field)
                            <input type="hidden" name="{{ $field }}" value="{{ request($field) }}">
                        @endforeach
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="ri-download-2-line"></i> Export to Excel
                        </button>
                    </form>
                </div>
            </form>

            <!-- DataTable -->
            <table class="table table-hover" id="stockTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Item Group</th>
                        <th>Stock Group</th>
                        <th>Metal Type</th>
                        <th>Purity</th>
                        <th>Gross Weight</th>
                        <th>Status</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @push('js')
        <script>
            $(function () {
                const table = $('#stockTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route("stock-items.data") }}',
                        data: function (d) {
                            d.item_group_id = $('#item_group_id').val();
                            d.stock_group_id = $('#stock_group_id').val();
                            d.metal_type_id = $('#metal_type_id').val();
                            d.purity_id = $('#purity_id').val();
                            d.status = $('#status').val();
                        }
                    },
                    columns: [
                        { data: 'code' },
                        { data: 'name' },
                        { data: 'item_group' },
                        { data: 'stock_group' },
                        { data: 'metal_type' },
                        { data: 'purity' },
                        { data: 'gross_weight' },
                        { data: 'status' },
                        { data: 'created_at' }
                    ],
                    order: [[8, 'desc']]
                });

                // Reload table on filter change
                $('#filterForm').on('change', 'select', function () {
                    table.ajax.reload();
                });
            });
        </script>
    @endpush
@endsection

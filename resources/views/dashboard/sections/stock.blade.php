@php
    $wt = fn ($v) => number_format((float) $v, 3);
    $pcs = fn ($v) => number_format((int) $v);
@endphp

<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Stock at a Glance</h5>
        <a href="{{ route('stock.index') }}" class="btn btn-sm btn-soft-primary">Full sheet</a>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="text-muted fs-12 text-uppercase">Pieces</div>
                <div class="fs-22 fw-bold">{{ $pcs($section['totals']->pcs) }}</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted fs-12 text-uppercase">Net Weight</div>
                <div class="fs-22 fw-bold">{{ $wt($section['totals']->net) }} g</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted fs-12 text-uppercase">Held for customers</div>
                <div class="fs-22 fw-bold">{{ $pcs($section['totals']->held) }}</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-centered table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Stock Group</th>
                        <th class="text-end">Pcs</th>
                        <th class="text-end">Net Wt</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ $pcs($row->pcs) }}</td>
                            <td class="text-end">{{ $wt($row->net) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

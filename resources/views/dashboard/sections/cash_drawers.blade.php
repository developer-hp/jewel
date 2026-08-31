@php
    $money = fn ($v) => number_format((float) $v, 2);
    // Opening + in − out. Never a stored figure: the same arithmetic CashMath does,
    // over columns that came from CashMath's own expressions.
    $closing = fn ($drawer) => (float) $drawer->opening_balance + (float) $drawer->cash_in - (float) $drawer->cash_out;
@endphp

<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cash Drawers</h5>
        <div class="d-flex gap-2">
            @can('cash_entry.view')
                <a href="#" class="btn btn-sm btn-soft-secondary" data-bs-toggle="modal"
                    data-bs-target="#cashCalculatorModal">
                    <i class="ri-calculator-line"></i> Count
                </a>
            @endcan
            <a href="{{ route('cash-drawers.index') }}" class="btn btn-sm btn-soft-primary">Drawers</a>
        </div>
    </div>

    <div class="card-body">
        {{-- The shop's whole till position, opening to closing, on one line. --}}
        <div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
            <div class="col">
                <div class="border rounded p-3 h-100">
                    <p class="text-muted text-uppercase mb-1 fs-12">Opening</p>
                    <h4 class="mb-0">{{ $money($section['opening']) }}</h4>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 h-100">
                    <p class="text-muted text-uppercase mb-1 fs-12">Cash In</p>
                    <h4 class="mb-0 text-success">+{{ $money($section['in']) }}</h4>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 h-100">
                    <p class="text-muted text-uppercase mb-1 fs-12">Cash Out</p>
                    <h4 class="mb-0 text-danger">&minus;{{ $money($section['out']) }}</h4>
                </div>
            </div>
            <div class="col">
                <div class="card bg-primary h-100 mb-0">
                    <div class="card-body p-3">
                        <p class="text-white text-uppercase mb-1 fs-12 opacity-75">Closing</p>
                        <h4 class="text-white mb-0">
                            {{ $money($section['opening'] + $section['in'] - $section['out']) }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- And the same four figures per till, so a discrepancy can be traced to one. --}}
        <div class="table-responsive">
            <table class="table table-sm table-centered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Drawer</th>
                        <th class="text-end">Opening</th>
                        <th class="text-end">In</th>
                        <th class="text-end">Out</th>
                        <th class="text-end">Closing</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['drawers'] as $drawer)
                        <tr>
                            <td>{{ $drawer->name }}</td>
                            <td class="text-end">{{ $money($drawer->opening_balance) }}</td>
                            <td class="text-end text-success">{{ $money($drawer->cash_in) }}</td>
                            <td class="text-end text-danger">{{ $money($drawer->cash_out) }}</td>
                            <td class="text-end fw-bold">{{ $money($closing($drawer)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Said plainly, because the cash entry screen records all three and only one
             of them lands in the till. --}}
        <p class="text-muted fs-12 mb-0 mt-2">
            Cash only &mdash; cheques and old gold settle a document but never reach a drawer.
        </p>
    </div>
</div>

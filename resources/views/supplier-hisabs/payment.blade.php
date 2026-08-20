@extends('layouts.app')

@section('title', 'Settle Hisab — '.$hisab->supplier_label)

@section('content')
    <x-page-title title="Settle Hisab" />

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card widget-icon-box bg-warning h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="text-white my-0">{{ $hisab->supplier_label }}</h3>
                        <span class="text-white-50">Supplier</span>
                    </div>
                    <div class="avatar-md bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center">
                        <i class="ri-user-3-fill fs-24 text-white"></i>
                    </div>
                </div>
            </div>
        </div>

        @foreach ([
            ['id' => 'card-fine-baki', 'label' => 'Fine Baki', 'value' => rtrim(rtrim(number_format((float) $hisab->fine_baki, 3, '.', ''), '0'), '.') ?: '0', 'class' => 'bg-dark'],
            ['id' => 'card-cash-baki', 'label' => 'Cash Baki', 'value' => number_format((float) $hisab->cash_baki, 2), 'class' => 'bg-success'],
            ['id' => 'card-fine-kapi', 'label' => 'Fine Kapi', 'value' => number_format($hisab->fineKapi(), 3), 'class' => 'bg-info'],
            ['id' => 'card-cash-apvi', 'label' => 'Cash Apvi', 'value' => number_format($hisab->cashApvi(), 2), 'class' => 'bg-warning'],
        ] as $card)
            <div class="col-md-2">
                <div class="card h-100">
                    <div class="card-header {{ $card['class'] }} text-white py-2">
                        <i class="ri-arrow-right-s-line"></i> {{ strtoupper($card['label']) }}
                    </div>
                    <div class="card-body text-center py-3">
                        <h3 class="my-0" id="{{ $card['id'] }}">{{ $card['value'] }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('supplier-hisabs.settle.update', $hisab) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="text-muted fs-13 mb-0">
                        Fine weight is gross &times; touch &divide; 100. Whatever fine is still owed once
                        these rows are counted becomes cash at
                        <strong>{{ number_format($ratePerGram, 2) }}</strong> per gram
                        ({{ number_format($ratePerGram * 10, 2) }} per 10 g).
                    </p>
                    <button type="button" class="btn btn-dark" id="hisab-add-row">
                        <i class="ri-add-line"></i> Add
                    </button>
                </div>

                @error('rows')
                    <div class="alert alert-danger py-2 fs-13">{{ $message }}</div>
                @enderror

                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0" id="hisab-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 34%">Item</th>
                                <th style="width: 20%">Gross Weight</th>
                                <th style="width: 18%">Touch</th>
                                <th style="width: 20%">Fine Weight</th>
                                <th style="width: 8%"></th>
                            </tr>
                        </thead>
                        <tbody id="hisab-body">
                            @foreach ($rows as $i => $row)
                                @include('supplier-hisabs.partials._payment-row', ['index' => $i, 'row' => $row])
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 justify-content-center mt-3">
                    <a href="{{ route('supplier-hisabs.index', ['date' => $hisab->hisab_date->toDateString()]) }}"
                        class="btn btn-warning">Cancel</a>
                    <button type="submit" class="btn btn-dark"><i class="ri-save-line"></i> Save</button>
                </div>
            </div>
        </div>
    </form>

    <template id="hisab-row-template">
        @include('supplier-hisabs.partials._payment-row', ['index' => '__INDEX__', 'row' => null])
    </template>
@endsection

@push('js')
    <script>
        $(function () {
            let nextIndex = {{ $rows->count() }};

            const fineBaki = {{ (float) $hisab->fine_baki }};
            const cashBaki = {{ (float) $hisab->cash_baki }};
            const ratePerGram = {{ $ratePerGram }};

            // Mirrors SupplierHisab::roundToTen — cash settles to the nearest ten.
            function roundToTen(amount) {
                return Math.round(amount / 10) * 10;
            }

            function refresh() {
                let fineKapi = 0;

                $('#hisab-body tr.hisab-row').each(function () {
                    const $row = $(this);
                    const gross = parseFloat($row.find('.hisab-gross').val()) || 0;
                    const touch = parseFloat($row.find('.hisab-touch').val()) || 0;
                    const fine = Math.round(gross * touch / 100 * 1000) / 1000;

                    $row.find('.hisab-fine').val(gross ? fine.toFixed(3) : '');
                    fineKapi += fine;
                });

                fineKapi = Math.round(fineKapi * 1000) / 1000;

                const remaining = Math.round((fineBaki - fineKapi) * 1000) / 1000;
                const cashApvi = roundToTen(remaining * ratePerGram + cashBaki);

                $('#card-fine-kapi').text(fineKapi.toFixed(3));
                $('#card-cash-apvi').text(cashApvi.toLocaleString('en-IN', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                }));
            }

            $('#hisab-add-row').on('click', function () {
                $('#hisab-body').append($('#hisab-row-template').html().replace(/__INDEX__/g, nextIndex++));
                refresh();
            });

            $(document).on('click', '.hisab-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            $(document).on('input change', '#hisab-body input', refresh);

            // Opens with one blank row ready to fill.
            if (nextIndex === 0) {
                $('#hisab-add-row').trigger('click');
            }

            refresh();
        });
    </script>
@endpush

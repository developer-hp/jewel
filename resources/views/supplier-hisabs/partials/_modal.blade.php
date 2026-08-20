{{--
    Add / edit hisab.

    Four fields do not warrant leaving the listing, so this is a modal — but it posts
    as an ordinary form rather than over AJAX. On failure the request redirects back
    with its own `hisab` error bag, and the script below reopens the modal on the row
    that failed.
--}}
@php($hisabErrors = $errors->getBag('hisab'))

<div class="modal fade" id="hisab-modal" tabindex="-1" aria-labelledby="hisab-modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('supplier-hisabs.store') }}" id="hisab-form" class="modal-content">
            @csrf
            {{-- Swapped to PUT by the script when a row is being edited. --}}
            <input type="hidden" name="_method" id="hisab-method" value="POST">
            <input type="hidden" name="hisab_id" id="hisab-id" value="{{ old('hisab_id') }}">

            <div class="modal-header py-2">
                <h5 class="modal-title" id="hisab-modal-title">Supplier Hisab</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3 row">
                    <label for="hisab-date" class="col-sm-3 col-form-label">Date <span class="text-danger">*</span></label>
                    <div class="col-sm-9">
                        {{-- Fixed to the day being viewed; move day with the picker above. --}}
                        <input type="date" id="hisab-date" name="hisab_date" class="form-control bg-light"
                            value="{{ old('hisab_date', $date->toDateString()) }}" readonly required>
                        @if ($hisabErrors->has('hisab_date'))
                            <div class="text-danger fs-13 mt-1">{{ $hisabErrors->first('hisab_date') }}</div>
                        @endif
                    </div>
                </div>

                <div class="mb-3 row">
                    <label for="hisab-supplier" class="col-sm-3 col-form-label">Supplier <span class="text-danger">*</span></label>
                    <div class="col-sm-9">
                        <select id="hisab-supplier" name="supplier_id" class="form-select" required>
                            <option value="">Select</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                    {{ $supplier->short_name ?: $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($hisabErrors->has('supplier_id'))
                            <div class="text-danger fs-13 mt-1">{{ $hisabErrors->first('supplier_id') }}</div>
                        @endif
                    </div>
                </div>

                <div class="mb-3 row">
                    <label for="hisab-fine" class="col-sm-3 col-form-label">Gold Wt</label>
                    <div class="col-sm-9">
                        <input type="number" step="0.001" min="0" id="hisab-fine" name="fine_baki"
                            class="form-control" value="{{ old('fine_baki') }}">
                        @if ($hisabErrors->has('fine_baki'))
                            <div class="text-danger fs-13 mt-1">{{ $hisabErrors->first('fine_baki') }}</div>
                        @endif
                    </div>
                </div>

                <div class="mb-3 row">
                    <label for="hisab-cash" class="col-sm-3 col-form-label">Amount</label>
                    <div class="col-sm-9">
                        <input type="number" step="0.01" min="0" id="hisab-cash" name="cash_baki"
                            class="form-control" value="{{ old('cash_baki') }}">
                        @if ($hisabErrors->has('cash_baki'))
                            <div class="text-danger fs-13 mt-1">{{ $hisabErrors->first('cash_baki') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark"><i class="ri-save-line"></i> Save</button>
            </div>
        </form>
    </div>
</div>

@push('js')
    <script>
        $(function () {
            const modal = new bootstrap.Modal('#hisab-modal');
            const storeUrl = '{{ route('supplier-hisabs.store') }}';
            // `__ID__` is swapped for the row being edited.
            const updateUrl = '{{ route('supplier-hisabs.update', ['hisab' => '__ID__']) }}';

            window.appSelect2('#hisab-supplier', { dropdownParent: $('#hisab-modal') });

            function open(values) {
                const editing = !!values.id;

                $('#hisab-modal-title').text(editing ? 'Edit Supplier Hisab' : 'Supplier Hisab');
                $('#hisab-form').attr('action', editing ? updateUrl.replace('__ID__', values.id) : storeUrl);
                $('#hisab-method').val(editing ? 'PUT' : 'POST');
                $('#hisab-id').val(values.id || '');
                $('#hisab-fine').val(values.fine != null ? values.fine : '');
                $('#hisab-cash').val(values.cash != null ? values.cash : '');
                $('#hisab-supplier').val(values.supplier || '').trigger('change');

                modal.show();
            }

            $('#hisab-add').on('click', () => open({}));

            $(document).on('click', '.hisab-edit', function () {
                const $b = $(this);
                open({ id: $b.data('id'), supplier: $b.data('supplier'), fine: $b.data('fine'), cash: $b.data('cash') });
            });

            // A failed save comes back here, so reopen on the row it came from.
            @if ($hisabErrors->any())
                open({
                    id: {{ old('hisab_id') ? (int) old('hisab_id') : 'null' }},
                    supplier: {{ old('supplier_id') ? (int) old('supplier_id') : 'null' }},
                    fine: {{ old('fine_baki') !== null ? (float) old('fine_baki') : 'null' }},
                    cash: {{ old('cash_baki') !== null ? (float) old('cash_baki') : 'null' }}
                });
            @endif
        });
    </script>
@endpush

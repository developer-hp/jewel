@extends('layouts.app')

@section('title', 'Print Sticker')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Print Sticker" />

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    {{-- GET with target=_blank: the picked ids go up as a query string
                         and the PDF opens in its own tab, leaving this screen ready for
                         the next batch. --}}
                    <form method="GET" action="{{ route('order-forms.sticker-by-ref') }}" target="_blank">
                        <div class="row mb-3">
                            <label for="order-picker" class="col-sm-3 col-form-label text-sm-end">
                                Orders <span class="text-danger">*</span>
                            </label>
                            <div class="col-sm-9">
                                <select id="order-picker" name="ids[]" class="form-control" multiple required></select>
                                <small class="text-muted">
                                    Search by reference or customer — <code>{{ $prefix }} 159</code>,
                                    <code>159</code> or a name. Pick as many as you like; more than one
                                    prints four to an A4 sheet.
                                </small>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-center">
                            <a href="{{ route('order-forms.index') }}" class="btn btn-warning">Cancel</a>
                            <button type="submit" class="btn btn-dark">
                                <i class="ri-printer-line"></i> Print
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(function () {
            window.appSelect2('#order-picker', {
                ajax: {
                    url: '{{ route('order-forms.sticker-search') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    // The endpoint already answers in select2's shape, so there is
                    // nothing to map here.
                    processResults: data => ({ results: data.results || [] }),
                },
                // Zero, not two: opening the box with the most recent orders already
                // listed is how the counter usually finds the one it wants.
                minimumInputLength: 0,
                placeholder: 'Search by reference or customer',
                allowClear: true,
            });

            // `required` on a select2 is invisible — the browser tries to focus a
            // hidden control and reports nothing. Check it here instead.
            $('#order-picker').closest('form').on('submit', function (event) {
                if (($('#order-picker').val() || []).length === 0) {
                    event.preventDefault();
                    $.NotificationApp.send('Nothing picked', 'Choose at least one order to print.',
                        'top-right', '#ff5b5b', 'error');
                }
            });
        });
    </script>
@endpush

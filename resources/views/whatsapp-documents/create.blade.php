@extends('layouts.app')

@section('title', 'Send Document')

@include('layouts.partials.select2-assets')

@section('content')
    <x-page-title title="Send Document">
        <x-slot:actions>
            <a href="{{ route('whatsapp-templates.index') }}" class="btn btn-light">
                <i class="ri-settings-3-line"></i> WhatsApp Settings
            </a>
        </x-slot:actions>
    </x-page-title>

    @include('whatsapp-templates.partials._warnings')

    @unless ($template?->isSendable())
        <div class="alert alert-warning">
            <i class="ri-error-warning-fill me-1"></i>
            The <strong>{{ $event->label() }}</strong> message is not switched on, so nothing
            can be sent from here yet.
            <a href="{{ route('whatsapp-templates.edit', $event->value) }}">Set it up</a>.
        </div>
    @endunless

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('whatsapp-documents.send') }}"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Customer</label>
                            <select id="customer_id" name="customer_id" class="form-select">
                                <option value=""></option>
                            </select>
                            <small class="text-muted">
                                Search by name or number. Leave it empty to send to someone who is
                                not on the register.
                            </small>
                            @error('customer_id')
                                <div class="text-danger fs-12">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_no" class="form-label">Or a number</label>
                                <input type="text" id="contact_no" name="contact_no"
                                    class="form-control @error('contact_no') is-invalid @enderror"
                                    value="{{ old('contact_no') }}" maxlength="30"
                                    placeholder="9601263350">
                                @error('contact_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="customer_name" class="form-label">Their name</label>
                                <input type="text" id="customer_name" name="customer_name"
                                    class="form-control @error('customer_name') is-invalid @enderror"
                                    value="{{ old('customer_name') }}" maxlength="150"
                                    placeholder="Used in the greeting">
                                @error('customer_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="described_as" class="form-label">
                                What is it? <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="described_as" name="described_as"
                                class="form-control @error('described_as') is-invalid @enderror"
                                value="{{ old('described_as') }}" maxlength="60" required
                                placeholder="Ledger Report">
                            <small class="text-muted">
                                Printed in the message: “Your <strong>Ledger Report</strong> is ready.”
                            </small>
                            @error('described_as')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="document" class="form-label">
                                PDF <span class="text-danger">*</span>
                            </label>
                            <input type="file" id="document" name="document" accept="application/pdf"
                                class="form-control @error('document') is-invalid @enderror" required>
                            <small class="text-muted">Up to 10 MB.</small>
                            @error('document')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @can('app_setting.edit')
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-whatsapp-line"></i> Send
                            </button>
                        @endcan
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">What they receive</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><span class="text-muted">[ PDF attached ]</span></p>
                    <p class="mb-2">Hello <strong>&lt;their name&gt;</strong> 👋</p>
                    <p class="mb-2">
                        Your <strong>&lt;what is it&gt;</strong> is ready. Please find the PDF
                        attached for your reference.
                    </p>
                    <p class="mb-0">Thank you for being with us! 😊</p>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="ri-links-line me-1"></i>
                WhatsApp fetches the PDF from this app itself, so it has to be reachable
                from the internet. On a local address like <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>
                the send will be rejected.
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(function () {
            // Same picker as the order and estimate screens.
            window.appSelect2($('#customer_id'), {
                ajax: {
                    url: '{{ route('customers.search') }}',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({
                        results: (data.customers || []).map(c => ({
                            id: c.id,
                            text: c.name + (c.phone ? ' — ' + c.phone : ''),
                            customer: c,
                        })),
                    }),
                },
                minimumInputLength: 0,
                placeholder: 'Search a customer…',
                allowClear: true,
            });

            // Filling these in makes it obvious who is being written to, and they are
            // what gets used if the customer is then cleared.
            $('#customer_id').on('select2:select', function (e) {
                const c = e.params.data.customer || {};
                $('#contact_no').val(c.phone || '');
                $('#customer_name').val(c.name || '');
            });
        });
    </script>
@endpush

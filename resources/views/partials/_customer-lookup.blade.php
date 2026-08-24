{{--
    Look the customer up by number as it is typed: someone already on the register
    fills in their own name and address, and a new one is added on save.

    $contactField the id of the mobile input
--}}
@can('customer.view')
    @push('js')
        <script>
            $(function () {
                const lookupUrl = '{{ route('customers.lookup') }}';
                let lastLookup = null;
                let timer = null;

                function apply(customer) {
                    const $hint = $('#customer-hint');

                    if (!customer) {
                        $hint.addClass('d-none').text('');
                        return;
                    }

                    // Never overwrite something already typed — the clerk may be
                    // correcting a name, and the register is not the authority.
                    if (!$('#customer_name').val().trim()) {
                        $('#customer_name').val(customer.name);
                    }

                    if (!$('#address').val().trim() && customer.address) {
                        $('#address').val(customer.address);
                    }

                    $hint.removeClass('d-none').text('Known customer: ' + customer.name);
                }

                function lookup() {
                    const phone = ($('#{{ $contactField }}').val() || '').replace(/\D+/g, '');

                    if (phone.length < 6 || phone === lastLookup) {
                        return;
                    }

                    lastLookup = phone;

                    $.getJSON(lookupUrl, { phone: phone })
                        .done(response => apply(response.customer))
                        .fail(() => $('#customer-hint').addClass('d-none'));
                }

                $('#{{ $contactField }}')
                    .on('input', function () {
                        clearTimeout(timer);
                        timer = setTimeout(lookup, 350);
                    })
                    .on('blur', lookup);
            });
        </script>
    @endpush
@endcan

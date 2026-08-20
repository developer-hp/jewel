{{-- Shared by the create and edit screens, so the autofill lives in one place. --}}
@push('js')
    <script>
        $(function () {
            window.appSelect2('#supplier_id', { placeholder: 'Search a supplier…' });

            // Picking a supplier fills the recipient in. The fields stay editable, and
            // what is saved is kept on the slip — editing the supplier later must not
            // rewrite a slip that has already gone out.
            $('#supplier_id').on('change', function () {
                const $option = $(this).find('option:selected');

                if (! $option.val()) {
                    return;
                }

                $('#name').val($option.data('name') || '');
                $('#city').val($option.data('city') || '');
                $('#mobile').val($option.data('mobile') || '');
                refreshPreview();
            });

            function refreshPreview() {
                $('#pv-name').text($('#name').val() || '—');
                $('#pv-city').text($('#city').val() || '—');
                $('#pv-mobile').text($('#mobile').val() || '—');
                $('#pv-insurance').text($('#insurance_amount').val() || '0');
            }

            $('#name, #city, #mobile, #insurance_amount').on('input', refreshPreview);
            refreshPreview();
        });
    </script>
@endpush

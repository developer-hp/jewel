{{--
    Searchable dropdowns, from the select2 bundled with the Jidox theme.

    The theme's app.min.css already carries .select2-container rules that match the
    Bootstrap form controls, so only the vendor files and an initialiser are needed.
    Include this from any page wanting a searchable select.
--}}

@push('css')
    <link href="{{ asset('theme/assets/vendor/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('js')
    <script src="{{ asset('theme/assets/vendor/select2/js/select2.full.min.js') }}"></script>

    <script>
        // Shared defaults for every searchable dropdown in the app.
        window.appSelect2 = function (selector, options) {
            return $(selector).select2($.extend(true, {
                width: '100%',
                placeholder: 'Search…',
                allowClear: true,
            }, options || {}));
        };
    </script>
@endpush

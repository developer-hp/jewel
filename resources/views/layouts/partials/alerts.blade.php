{{--
    Only the validation list still occupies the page.

    A one-line "Saved." or "Deleted." now arrives as a toast (layouts/partials/ui-feedback),
    which is what the theme's ui-notifications page uses and what keeps the top of a form
    from jumping down a row on every save. A validation failure is different: it is a list
    the user has to work through, so it stays put until they dismiss it.
--}}
@if ($errors->any() && ! request()->routeIs('login'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{--
    The two things that silently stop messages going out. Both are worth saying on
    every screen in this section, because neither shows up as an error anywhere the
    counter would see it.
--}}
@unless ($credentialsConfigured)
    <div class="alert alert-danger">
        <i class="ri-error-warning-fill me-1"></i>
        <strong>No credentials.</strong>
        <code>WA_TOKEN</code> and <code>WA_PHONE_ID</code> are not set in <code>.env</code>.
        Nothing will be sent until they are, however these templates are configured.
    </div>
@endunless

<div class="alert alert-warning">
    <i class="ri-information-fill me-1"></i>
    Messages are queued, and only leave the building while a queue worker is running
    (<code>php artisan queue:work</code>). Without one they wait in the
    <code>jobs</code> table indefinitely.
</div>

{{--
    The tap-to-call buttons. Shared by both landing layouts.

    The label keeps whatever punctuation the shop typed; only the tel: href is
    stripped down to digits, because that is what a dialler needs.
--}}
<div class="call-grid">
    @foreach ($settings->landingPhones() as $i => $phone)
        <a class="call-btn call-btn--{{ $i % 3 }} sr d{{ min($i + 1, 5) }}"
            href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">
            <span class="call-icon" aria-hidden="true"><i class="ri-phone-fill"></i></span>
            <span class="call-number">{{ $phone }}</span>
        </a>
    @endforeach
</div>

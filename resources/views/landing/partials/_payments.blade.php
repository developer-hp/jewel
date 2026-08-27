{{--
    The bank details and payment QR, side by side.

    Shared by both landing layouts. Whichever panel survives takes the full width, so
    a shop with no QR does not get a bank panel stranded in half the page.

    Expects $bank and $qr, resolved by the caller so it can decide whether to render
    the surrounding section at all.
--}}
<div class="panels @if ($bank === [] || ! $qr) panels--single @endif">
    @if ($bank !== [])
        <div class="panel sr d1">
            <h3><i class="ri-bank-line" aria-hidden="true"></i> Bank Account Details</h3>
            <dl class="bank">
                @foreach ($bank as $label => $value)
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                @endforeach
            </dl>
        </div>
    @endif

    @if ($qr)
        <div class="panel panel--qr sr d2">
            <h3><i class="ri-qr-code-line" aria-hidden="true"></i> Payment QR</h3>
            <img src="{{ $qr }}" alt="Payment QR code">
            <p class="quiet">Scan with any UPI app</p>
        </div>
    @endif
</div>

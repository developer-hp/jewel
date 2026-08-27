{{--
    The rate cards.

    Shared by both landing layouts: identical markup and class names, styled
    differently by landing-fancy.css and landing-simple.css. That is what stops a
    change here appearing on one look and not the other.
--}}
<div class="rates">
    @forelse ($rates as $i => $rate)
        {{-- Colour cycles through the palette by position, so the set always looks
             deliberate however many rates are published. --}}
        <article class="rate rate--{{ $i % 6 }} sr d{{ min($i + 1, 5) }}">
            <span class="rate-chip" aria-hidden="true">Rs</span>
            <p class="rate-value">
                {{ number_format((float) $rate['rate'], 0) }}<span
                    class="rate-note">{{ $settings->landing_rate_note }}</span>
            </p>
            <p class="rate-label">{{ $rate['label'] }} Rate</p>
        </article>
    @empty
        <p class="quiet">Rates will be published shortly.</p>
    @endforelse
</div>

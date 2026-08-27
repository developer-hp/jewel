{{-- The social pills. Shared by both landing layouts. --}}
@if ($settings->socialLinks() !== [])
    <div class="socials">
        @foreach ($settings->socialLinks() as $link)
            <a class="social social--{{ str_replace('social_', '', $link['key']) }}"
                href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                title="{{ $link['label'] }}" aria-label="{{ $link['label'] }}">
                <i class="{{ $link['icon'] }}" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@endif

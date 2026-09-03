{{--
    The jump-to menu, as a grid of grouped links.

    Shared by the Ctrl+M palette and by the dashboard of anyone who cannot see the
    overview — one markup, so the two can never drift into looking like different
    things.

    Contents come from App\Support\CommandPalette, which has already dropped anything
    this user cannot reach, so nothing here needs its own permission check.

    $groups   from CommandPalette::groups()
    $filter   true to mark items up for the palette's type-to-filter
--}}
@php($filterable = $filter ?? false)

@foreach ($groups as $group)
    <div class="palette-group" @if ($filterable) data-palette-group @endif>
        <div class="palette-group-title text-{{ $group['accent'] }}">
            <span>{{ $group['title'] }}</span>
        </div>

        <div class="palette-grid">
            @foreach ($group['items'] as $entry)
                <a href="{{ $entry['url'] }}"
                    class="palette-item palette-{{ $group['accent'] }} {{ $entry['active'] ? 'is-current' : '' }}"
                    @if ($filterable)
                        data-palette-item
                        data-palette-text="{{ Str::lower($entry['label'] . ' ' . $entry['hint']) }}"
                    @endif>
                    <span class="palette-icon">
                        <i class="{{ $entry['icon'] }}"></i>
                    </span>
                    <span class="palette-text">
                        <span class="palette-label">{{ $entry['label'] }}</span>
                        <span class="palette-hint">{{ $entry['hint'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endforeach

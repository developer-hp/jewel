{{--
    One sidebar entry: either a plain link or a collapsible group.

    $item comes from App\Support\SidebarMenu, already filtered by permission with
    its active/open state resolved.
--}}
@if ($item['type'] === 'group')
    <li class="side-nav-item {{ $item['open'] ? 'menuitem-active' : '' }}">
        <a data-bs-toggle="collapse" href="#{{ $item['id'] }}" aria-expanded="{{ $item['open'] ? 'true' : 'false' }}"
            aria-controls="{{ $item['id'] }}" class="side-nav-link {{ $item['open'] ? 'active' : '' }}">
            <i class="{{ $item['icon'] }}"></i>
            <span> {{ $item['label'] }} </span>
            <span class="menu-arrow"></span>
        </a>

        <div class="collapse {{ $item['open'] ? 'show' : '' }}" id="{{ $item['id'] }}">
            <ul class="side-nav-second-level">
                @foreach ($item['children'] as $child)
                    <li class="{{ $child['active'] ? 'menuitem-active' : '' }}">
                        <a href="{{ $child['url'] }}" class="{{ $child['active'] ? 'active' : '' }}">
                            {{ $child['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </li>
@else
    <li class="side-nav-item {{ $item['active'] ? 'menuitem-active' : '' }}">
        <a href="{{ $item['url'] }}" class="side-nav-link {{ $item['active'] ? 'active' : '' }}">
            <i class="{{ $item['icon'] }}"></i>
            <span> {{ $item['label'] }} </span>
        </a>
    </li>
@endif

@php($user = auth()->user())

<div class="leftside-menu">

    <a href="{{ route('dashboard') }}" class="logo logo-light">
        <span class="logo-lg"><img src="{{ $appSettings->logoUrl('logo_path') }}" alt="logo"></span>
        <span class="logo-sm"><img src="{{ $appSettings->logoUrl('logo_small_path') }}" alt="small logo"></span>
    </a>

    <a href="{{ route('dashboard') }}" class="logo logo-dark">
        <span class="logo-lg"><img src="{{ $appSettings->logoUrl('logo_dark_path') }}" alt="dark logo"></span>
        <span class="logo-sm"><img src="{{ $appSettings->logoUrl('logo_small_path') }}" alt="small logo"></span>
    </a>

    <div class="button-sm-hover" data-bs-toggle="tooltip" data-bs-placement="right" title="Show Full Sidebar">
        <i class="ri-checkbox-blank-circle-line align-middle"></i>
    </div>

    <div class="button-close-fullsidebar">
        <i class="ri-close-fill align-middle"></i>
    </div>

    <div class="h-100" id="leftside-menu-container" data-simplebar>

        <div class="leftbar-user p-3 text-white">
            <a href="{{ route('profile.edit') }}" class="d-flex align-items-center text-reset">
                <div class="flex-shrink-0">
                    <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('theme/assets/images/users/avatar-1.jpg') }}"
                        alt="user-image" height="42" class="rounded-circle shadow">
                </div>
                <div class="flex-grow-1 ms-2">
                    <span class="fw-semibold fs-15 d-block">{{ $user->name }}</span>
                    <span class="fs-13">{{ $user->roleLabel() }}</span>
                </div>
                <div class="ms-auto">
                    <i class="ri-arrow-right-s-fill fs-20"></i>
                </div>
            </a>
        </div>

        <ul class="side-nav">

            <li class="side-nav-title mt-1">Main</li>

            <li class="side-nav-item {{ request()->routeIs('dashboard') ? 'menuitem-active' : '' }}">
                <a href="{{ route('dashboard') }}"
                    class="side-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="ri-dashboard-2-fill"></i>
                    <span> Dashboard </span>
                </a>
            </li>

            @can('item.view')
                <li class="side-nav-item {{ request()->routeIs('items.*') ? 'menuitem-active' : '' }}">
                    <a href="{{ route('items.index') }}"
                        class="side-nav-link {{ request()->routeIs('items.*') ? 'active' : '' }}">
                        <i class="ri-price-tag-3-fill"></i>
                        <span> Items </span>
                    </a>
                </li>
            @endcan

            @canany(['metal_type.view', 'purity.view', 'metal_rate.view', 'item_group.view', 'stone.view', 'making_charge.view', 'supplier.view', 'label_setting.view', 'app_setting.view'])
                <li class="side-nav-title mt-1">Masters</li>

                @can('metal_rate.view')
                    <li class="side-nav-item {{ request()->routeIs('rates.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('rates.today') }}"
                            class="side-nav-link {{ request()->routeIs('rates.*') ? 'active' : '' }}">
                            <i class="ri-exchange-funds-fill"></i>
                            <span> Daily Rates </span>
                        </a>
                    </li>
                @endcan

                @can('metal_type.view')
                    <li class="side-nav-item {{ request()->routeIs('metal-types.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('metal-types.index') }}"
                            class="side-nav-link {{ request()->routeIs('metal-types.*') ? 'active' : '' }}">
                            <i class="ri-copper-coin-fill"></i>
                            <span> Metal Types </span>
                        </a>
                    </li>
                @endcan

                @can('purity.view')
                    <li class="side-nav-item {{ request()->routeIs('purities.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('purities.index') }}"
                            class="side-nav-link {{ request()->routeIs('purities.*') ? 'active' : '' }}">
                            <i class="ri-percent-fill"></i>
                            <span> Purities </span>
                        </a>
                    </li>
                @endcan

                @can('item_group.view')
                    <li class="side-nav-item {{ request()->routeIs('item-groups.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('item-groups.index') }}"
                            class="side-nav-link {{ request()->routeIs('item-groups.*') ? 'active' : '' }}">
                            <i class="ri-folder-2-fill"></i>
                            <span> Item Groups </span>
                        </a>
                    </li>
                @endcan

                @can('stone.view')
                    <li class="side-nav-item {{ request()->routeIs('stones.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('stones.index') }}"
                            class="side-nav-link {{ request()->routeIs('stones.*') ? 'active' : '' }}">
                            <i class="ri-shining-2-fill"></i>
                            <span> Stones </span>
                        </a>
                    </li>

                    <li class="side-nav-item {{ request()->routeIs('diamonds.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('diamonds.index') }}"
                            class="side-nav-link {{ request()->routeIs('diamonds.*') ? 'active' : '' }}">
                            <i class="ri-vip-diamond-fill"></i>
                            <span> Diamonds </span>
                        </a>
                    </li>
                @endcan

                @can('making_charge.view')
                    <li class="side-nav-item {{ request()->routeIs('making-charges.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('making-charges.index') }}"
                            class="side-nav-link {{ request()->routeIs('making-charges.*') ? 'active' : '' }}">
                            <i class="ri-hammer-fill"></i>
                            <span> Making Charges </span>
                        </a>
                    </li>
                @endcan

                @can('supplier.view')
                    <li class="side-nav-item {{ request()->routeIs('suppliers.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('suppliers.index') }}"
                            class="side-nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                            <i class="ri-truck-fill"></i>
                            <span> Suppliers </span>
                        </a>
                    </li>
                @endcan

                @can('app_setting.view')
                    <li class="side-nav-item {{ request()->routeIs('security-settings.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('security-settings.edit') }}"
                            class="side-nav-link {{ request()->routeIs('security-settings.*') ? 'active' : '' }}">
                            <i class="ri-shield-keyhole-fill"></i>
                            <span> Security </span>
                        </a>
                    </li>
                @endcan

                @can('app_setting.view')
                    <li class="side-nav-item {{ request()->routeIs('app-settings.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('app-settings.edit') }}"
                            class="side-nav-link {{ request()->routeIs('app-settings.*') ? 'active' : '' }}">
                            <i class="ri-palette-fill"></i>
                            <span> Appearance </span>
                        </a>
                    </li>
                @endcan

                @can('label_setting.view')
                    <li class="side-nav-item {{ request()->routeIs('label-settings.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('label-settings.edit') }}"
                            class="side-nav-link {{ request()->routeIs('label-settings.*') ? 'active' : '' }}">
                            <i class="ri-price-tag-3-fill"></i>
                            <span> Label Settings </span>
                        </a>
                    </li>
                @endcan
            @endcanany

            @canany(['user.view', 'role.view', 'permission.view'])
                <li class="side-nav-title mt-1">Administration</li>

                @can('user.view')
                    <li class="side-nav-item {{ request()->routeIs('users.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('users.index') }}"
                            class="side-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="ri-group-fill"></i>
                            <span> Users </span>
                        </a>
                    </li>
                @endcan

                @can('role.view')
                    <li class="side-nav-item {{ request()->routeIs('roles.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('roles.index') }}"
                            class="side-nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                            <i class="ri-shield-user-fill"></i>
                            <span> Roles </span>
                        </a>
                    </li>
                @endcan

                @can('permission.view')
                    <li class="side-nav-item {{ request()->routeIs('permissions.*') ? 'menuitem-active' : '' }}">
                        <a href="{{ route('permissions.index') }}"
                            class="side-nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                            <i class="ri-key-2-fill"></i>
                            <span> Permissions </span>
                        </a>
                    </li>
                @endcan
            @endcanany

            <li class="side-nav-title mt-1">Account</li>

            <li class="side-nav-item {{ request()->routeIs('profile.*') ? 'menuitem-active' : '' }}">
                <a href="{{ route('profile.edit') }}"
                    class="side-nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <i class="ri-account-circle-fill"></i>
                    <span> My Account </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('profile.edit') }}#change-password" class="side-nav-link">
                    <i class="ri-lock-password-fill"></i>
                    <span> Change Password </span>
                </a>
            </li>

            <li class="side-nav-item">
                {{-- Logout is a POST; the topbar already renders the form this submits. --}}
                <a href="{{ route('logout') }}" class="side-nav-link"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="ri-logout-box-fill"></i>
                    <span> Logout </span>
                </a>
            </li>

        </ul>

        <div class="clearfix"></div>
    </div>
</div>

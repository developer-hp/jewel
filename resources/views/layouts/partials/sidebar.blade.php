@php($user = auth()->user())

<div class="leftside-menu">

    <a href="{{ route('dashboard') }}" class="logo logo-light">
        <span class="logo-lg"><img src="{{ asset('theme/assets/images/logo.png') }}" alt="logo"></span>
        <span class="logo-sm"><img src="{{ asset('theme/assets/images/logo-sm.png') }}" alt="small logo"></span>
    </a>

    <a href="{{ route('dashboard') }}" class="logo logo-dark">
        <span class="logo-lg"><img src="{{ asset('theme/assets/images/logo-dark.png') }}" alt="dark logo"></span>
        <span class="logo-sm"><img src="{{ asset('theme/assets/images/logo-sm.png') }}" alt="small logo"></span>
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

        </ul>

        <div class="clearfix"></div>
    </div>
</div>

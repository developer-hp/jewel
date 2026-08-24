@php($user = auth()->user())

<!-- ========== Topbar Start ========== -->
<div class="navbar-custom">
    <div class="topbar container-fluid">
        <div class="d-flex align-items-center gap-lg-2 gap-1">

            <!-- Topbar Brand Logo -->
            <div class="logo-topbar">
                <a href="{{ route('dashboard') }}" class="logo-light">
                    <span class="logo-lg">
                        <img src="{{ $appSettings->logoUrl('logo_path') }}" alt="logo">
                    </span>
                    <span class="logo-sm">
                        <img src="{{ $appSettings->logoUrl('logo_small_path') }}" alt="small logo">
                    </span>
                </a>

                <a href="{{ route('dashboard') }}" class="logo-dark">
                    <span class="logo-lg">
                        <img src="{{ $appSettings->logoUrl('logo_dark_path') }}" alt="dark logo">
                    </span>
                    <span class="logo-sm">
                        <img src="{{ $appSettings->logoUrl('logo_small_path') }}" alt="small logo">
                    </span>
                </a>
            </div>

            <!-- Sidebar Menu Toggle Button -->
            <button class="button-toggle-menu">
                <i class="ri-menu-2-fill"></i>
            </button>
        </div>

        <ul class="topbar-menu d-flex align-items-center gap-3">
            {{-- A shortcut nobody can find is a shortcut nobody uses, so Ctrl+M
                 also has a button, with the keys printed on it. --}}
            <li class="d-none d-md-inline-block">
                <a class="nav-link d-flex align-items-center gap-2" href="#" data-command-palette-open
                    title="Go to… (Ctrl+M)">
                    <i class="ri-search-line fs-22"></i>
                    <span class="fs-12 text-muted d-none d-lg-inline">Ctrl + M</span>
                </a>
            </li>

            <li class="d-none d-sm-inline-block">
                <div class="nav-link" id="light-dark-mode">
                    <i class="ri-moon-fill fs-22"></i>
                </div>
            </li>

            <li class="d-none d-md-inline-block">
                <a class="nav-link" href="" data-toggle="fullscreen">
                    <i class="ri-fullscreen-line fs-22"></i>
                </a>
            </li>

            <li class="dropdown me-md-2">
                <a class="nav-link dropdown-toggle arrow-none nav-user px-2" data-bs-toggle="dropdown" href="#"
                    role="button" aria-haspopup="false" aria-expanded="false">
                    <span class="account-user-avatar">
                        <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('theme/assets/images/users/avatar-1.jpg') }}"
                            alt="user-image" width="32" class="rounded-circle">
                    </span>
                    <span class="d-lg-flex flex-column gap-1 d-none">
                        <h5 class="my-0">{{ $user->name }}</h5>
                        <h6 class="my-0 fw-normal">{{ $user->roleLabel() }}</h6>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated profile-dropdown">
                    <div class="dropdown-header noti-title">
                        <h6 class="text-overflow m-0">Welcome, {{ $user->username }}!</h6>
                    </div>

                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="ri-account-circle-fill align-middle me-1"></i>
                        <span>My Account</span>
                    </a>

                    <a href="{{ route('profile.edit') }}#change-password" class="dropdown-item">
                        <i class="ri-lock-password-fill align-middle me-1"></i>
                        <span>Change Password</span>
                    </a>

                    <div class="dropdown-divider"></div>

                    <a href="{{ route('logout') }}" class="dropdown-item"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ri-logout-box-fill align-middle me-1"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </li>
        </ul>
    </div>
</div>
<!-- ========== Topbar End ========== -->

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>

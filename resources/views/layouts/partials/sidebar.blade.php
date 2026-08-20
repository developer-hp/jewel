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

        {{-- Built from config/menu.php. App\Support\SidebarMenu has already dropped
             anything this user cannot reach, including whole groups and any section
             left empty by that, so there is nothing to gate here. --}}
        <ul class="side-nav">
            @foreach (App\Support\SidebarMenu::sections() as $section)
                <li class="side-nav-title mt-1">{{ $section['title'] }}</li>

                @foreach ($section['items'] as $item)
                    @include('layouts.partials._menu-item', ['item' => $item])
                @endforeach
            @endforeach
        </ul>

        <div class="clearfix"></div>
    </div>
</div>

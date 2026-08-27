@extends('layouts.app')

@section('title', 'Appearance')

@php
    $logoSlots = [
        [
            'field' => 'logo',
            'column' => 'logo_path',
            'label' => 'Logo — dark backgrounds',
            'hint' => 'Shown on the dark sidebar and topbar. Use a light or white logo.',
            'preview' => 'bg-dark',
        ],
        [
            'field' => 'logo_dark',
            'column' => 'logo_dark_path',
            'label' => 'Logo — light backgrounds',
            'hint' => 'Shown in light mode and on the login page. Use a dark logo.',
            'preview' => 'bg-light border',
        ],
        [
            'field' => 'logo_small',
            'column' => 'logo_small_path',
            'label' => 'Small logo / icon',
            'hint' => 'Shown when the sidebar is collapsed. Square works best.',
            'preview' => 'bg-dark',
        ],
    ];
@endphp

@section('content')
    <x-page-title title="Appearance" />

    <form method="POST" action="{{ route('app-settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Tabbed rather than one long column: ten cards stacked in a col-lg-7
             left half the page empty and the page itself a scroll. Every pane uses
             the full width, and each is one screen. --}}
        <ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="#tab-general" data-bs-toggle="tab" aria-expanded="true" role="tab"
                    class="nav-link active">
                    <i class="ri-settings-3-line me-1"></i> General
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="#tab-look" data-bs-toggle="tab" aria-expanded="false" role="tab"
                    class="nav-link">
                    <i class="ri-palette-line me-1"></i> Look & Feel
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="#tab-documents" data-bs-toggle="tab" aria-expanded="false" role="tab"
                    class="nav-link">
                    <i class="ri-file-list-3-line me-1"></i> Documents
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="#tab-landing" data-bs-toggle="tab" aria-expanded="false" role="tab"
                    class="nav-link">
                    <i class="ri-global-line me-1"></i> Landing Page
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Application</h5>
                            </div>
                            <div class="card-body">
                                <label for="app_name" class="form-label">App Name <span class="text-danger">*</span></label>
                                <input type="text" id="app_name" name="app_name"
                                    class="form-control @error('app_name') is-invalid @enderror"
                                    value="{{ old('app_name', $settings->app_name) }}" maxlength="60" required>
                                <small class="text-muted">Shown in the browser tab and the page footer.</small>
                                @error('app_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <hr>

                                <label for="media_disk" class="form-label">Photo Storage</label>
                                <select id="media_disk" name="media_disk"
                                    class="form-select @error('media_disk') is-invalid @enderror">
                                    @foreach (\App\Models\AppSetting::MEDIA_DISKS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('media_disk', $settings->media_disk) === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Where new item photos are written. S3 credentials come from <code>.env</code>, not this
                                    screen. Photos already uploaded keep serving from the disk they were written to, so
                                    switching is safe.
                                </small>
                                @error('media_disk')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Firm Details</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    Your own name and number. Prints as the FROM block on an angadiya slip.
                                </p>

                                <div class="row">
                                    <div class="col-md-5 mb-3">
                                        <label for="firm_name" class="form-label">Firm Name</label>
                                        <input type="text" id="firm_name" name="firm_name"
                                            class="form-control @error('firm_name') is-invalid @enderror"
                                            value="{{ old('firm_name', $settings->firm_name) }}" maxlength="100"
                                            placeholder="Firm Name">
                                        @error('firm_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="firm_city" class="form-label">City</label>
                                        <input type="text" id="firm_city" name="firm_city"
                                            class="form-control @error('firm_city') is-invalid @enderror"
                                            value="{{ old('firm_city', $settings->firm_city) }}" maxlength="100"
                                            placeholder="AHD">
                                        @error('firm_city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="firm_phone" class="form-label">Phone</label>
                                        <input type="text" id="firm_phone" name="firm_phone"
                                            class="form-control @error('firm_phone') is-invalid @enderror"
                                            value="{{ old('firm_phone', $settings->firm_phone) }}" maxlength="30"
                                            placeholder="079 26925755">
                                        @error('firm_phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="firm_website" class="form-label">Website</label>
                                        <input type="text" id="firm_website" name="firm_website"
                                            class="form-control @error('firm_website') is-invalid @enderror"
                                            value="{{ old('firm_website', $settings->firm_website) }}" maxlength="150"
                                            placeholder="http://example.com">
                                        <small class="text-muted">Printed under the phone on a repair form.</small>
                                        @error('firm_website')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="firm_office_phone" class="form-label">Office phone</label>
                                        <input type="text" id="firm_office_phone" name="firm_office_phone"
                                            class="form-control @error('firm_office_phone') is-invalid @enderror"
                                            value="{{ old('firm_office_phone', $settings->firm_office_phone) }}" maxlength="30"
                                            placeholder="+91-7874655115">
                                        <small class="text-muted">
                                            Shown on the office copy of a repair form. Falls back to the phone above.
                                        </small>
                                        @error('firm_office_phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <div class="alert alert-light border mb-0 fs-13">
                                            Prints as:
                                            <strong>FROM / {{ trim(($settings->firm_name ?? '') . ' ' . ($settings->firm_city ?? '')) ?: 'not set' }}
                                                / {{ $settings->firm_phone ?: 'no phone' }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Logos</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    PNG, JPG, WEBP or SVG, up to 1 MB. Leave a slot empty to keep the bundled theme logo.
                                </p>

                                @foreach ($logoSlots as $slot)
                                    <div class="mb-3 pb-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                        <label for="{{ $slot['field'] }}" class="form-label">{{ $slot['label'] }}</label>

                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="{{ $slot['preview'] }} rounded p-2 text-center"
                                                style="min-width: 140px;">
                                                <img src="{{ $settings->logoUrl($slot['column']) }}"
                                                    alt="{{ $slot['label'] }}" style="max-height: 28px; max-width: 120px;">
                                            </div>

                                            <div class="flex-grow-1">
                                                <input type="file" id="{{ $slot['field'] }}" name="{{ $slot['field'] }}"
                                                    class="form-control @error($slot['field']) is-invalid @enderror"
                                                    accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                                <small class="text-muted">{{ $slot['hint'] }}</small>
                                                @error($slot['field'])
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        @if ($settings->hasCustomLogo($slot['column']))
                                            <div class="form-check">
                                                <input type="hidden" name="remove_{{ $slot['field'] }}" value="0">
                                                <input class="form-check-input" type="checkbox"
                                                    id="remove_{{ $slot['field'] }}" name="remove_{{ $slot['field'] }}"
                                                    value="1">
                                                <label class="form-check-label text-danger" for="remove_{{ $slot['field'] }}">
                                                    Remove this logo and go back to the theme default
                                                </label>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-look" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Table Header</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    Applies to every table in the app. Two colours because a header
                                    that reads well in light mode is usually wrong in dark mode. Header
                                    text switches between black and white automatically.
                                </p>

                                @foreach ([['light', 'Light mode'], ['dark', 'Dark mode']] as [$mode, $label])
                                    @php($current = $settings->{"table_header_bg_{$mode}"})
                                    @php($default = \App\Models\AppSetting::defaultTableHeaderColours($mode))
                                    @php($sample = $default['bg'] ?? '#f2f2f7')
                                    <div class="mb-3">
                                        <label for="table_header_bg_{{ $mode }}" class="form-label">{{ $label }}</label>

                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color"
                                                id="table_header_bg_{{ $mode }}_picker"
                                                value="{{ old("table_header_bg_{$mode}", $current) ?: $sample }}">
                                            <input type="text" name="table_header_bg_{{ $mode }}"
                                                id="table_header_bg_{{ $mode }}"
                                                class="form-control thead-colour @error("table_header_bg_{$mode}") is-invalid @enderror"
                                                value="{{ old("table_header_bg_{$mode}", $current) }}" maxlength="7"
                                                data-mode="{{ $mode }}" placeholder="theme default">
                                        </div>

                                        <div class="form-check mt-1">
                                            <input class="form-check-input thead-default" type="checkbox"
                                                id="table_header_default_{{ $mode }}"
                                                name="table_header_default_{{ $mode }}" value="1"
                                                data-mode="{{ $mode }}" @checked(! $current)>
                                            <label class="form-check-label fs-13" for="table_header_default_{{ $mode }}">
                                                Use the app default
                                                @if ($default)
                                                    <span class="badge ms-1"
                                                        style="background-color: {{ $default['bg'] }};@if ($default['text']) color: {{ $default['text'] }};@endif">
                                                        {{ $default['bg'] }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">(theme grey)</span>
                                                @endif
                                            </label>
                                        </div>

                                        @error("table_header_bg_{$mode}")
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror

                                        {{-- Preview drawn with the same markup a real table header uses. --}}
                                        <table class="table table-sm mt-2 mb-0" style="pointer-events: none;">
                                            <thead>
                                                <tr id="thead-preview-{{ $mode }}">
                                                    <th>Code</th>
                                                    <th>Name</th>
                                                    <th class="text-end">Weight</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>RNG0001</td>
                                                    <td>Ring</td>
                                                    <td class="text-end">10.250</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Sidebar User Panel</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    The block at the top of the sidebar showing the signed-in user.
                                </p>

                                {{-- Live preview, built from the same markup as the real panel. --}}
                                <div class="leftbar-user p-3 rounded mb-3" id="sidebar-preview" style="display: block;">
                                    <div class="d-flex align-items-center">
                                        <img src="{{ auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : asset('theme/assets/images/users/avatar-1.jpg') }}"
                                            alt="user" height="42" class="rounded-circle shadow">
                                        <div class="ms-2">
                                            <span class="fw-semibold fs-15 d-block" id="preview-name">{{ auth()->user()->name }}</span>
                                            <span class="fs-13" id="preview-role">{{ auth()->user()->roleLabel() }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sidebar_user_bg_from" class="form-label">Gradient Start</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color"
                                                id="sidebar_user_bg_from_picker"
                                                value="{{ old('sidebar_user_bg_from', $settings->sidebar_user_bg_from) }}">
                                            <input type="text" name="sidebar_user_bg_from" id="sidebar_user_bg_from"
                                                class="form-control @error('sidebar_user_bg_from') is-invalid @enderror"
                                                value="{{ old('sidebar_user_bg_from', $settings->sidebar_user_bg_from) }}"
                                                maxlength="7">
                                        </div>
                                        @error('sidebar_user_bg_from')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="sidebar_user_bg_to" class="form-label">Gradient End</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color"
                                                id="sidebar_user_bg_to_picker"
                                                value="{{ old('sidebar_user_bg_to', $settings->sidebar_user_bg_to) }}">
                                            <input type="text" name="sidebar_user_bg_to" id="sidebar_user_bg_to"
                                                class="form-control @error('sidebar_user_bg_to') is-invalid @enderror"
                                                value="{{ old('sidebar_user_bg_to', $settings->sidebar_user_bg_to) }}"
                                                maxlength="7">
                                        </div>
                                        @error('sidebar_user_bg_to')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="sidebar_user_text_color" class="form-label">Text Colour</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color"
                                                id="sidebar_user_text_color_picker"
                                                value="{{ old('sidebar_user_text_color', $settings->sidebar_user_text_color) }}">
                                            <input type="text" name="sidebar_user_text_color" id="sidebar_user_text_color"
                                                class="form-control @error('sidebar_user_text_color') is-invalid @enderror"
                                                value="{{ old('sidebar_user_text_color', $settings->sidebar_user_text_color) }}"
                                                maxlength="7">
                                        </div>
                                        @error('sidebar_user_text_color')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-light w-100" id="reset-colours">
                                            <i class="ri-refresh-line"></i> Theme Default
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Dashboard</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    Which sections the dashboard shows. This is shared by everyone.
                                    A section is also skipped for anyone whose role cannot reach what
                                    it reports, and one with nothing to show does not appear at all.
                                </p>

                                {{-- Present even when every box is unticked, so the server is told
                                     "none" rather than nothing at all. --}}
                                <input type="hidden" name="dashboard_sections[]" value="">

                                @php($hidden = $settings->hiddenDashboardSections())

                                <div class="row row-cols-1 row-cols-md-2 g-2">
                                    @foreach (config('dashboard', []) as $section)
                                        <div class="col">
                                            <div class="form-check">
                                                <input class="form-check-input dashboard-pick" type="checkbox"
                                                    name="dashboard_sections[]" value="{{ $section['key'] }}"
                                                    id="section-{{ $section['key'] }}"
                                                    @checked(! in_array($section['key'], $hidden, true))>
                                                <label class="form-check-label" for="section-{{ $section['key'] }}">
                                                    {{ $section['label'] }}
                                                    <small class="text-muted d-block fs-12">{{ $section['hint'] }}</small>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="d-flex gap-2 mt-3">
                                    <button type="button" class="btn btn-sm btn-light" id="sections-all">Select all</button>
                                    <button type="button" class="btn btn-sm btn-light" id="sections-none">Select none</button>
                                </div>

                                <hr>

                                {{-- The settings row is read on every request, so holding it in the
                                     cache is the one query most worth avoiding. Off by default;
                                     turning it on is a deliberate choice. --}}
                                <div class="form-check form-switch">
                                    <input type="hidden" name="settings_cache_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" id="settings_cache_enabled"
                                        name="settings_cache_enabled" value="1"
                                        @checked(old('settings_cache_enabled', $settings->settings_cache_enabled))>
                                    <label class="form-check-label" for="settings_cache_enabled">
                                        Cache these settings
                                    </label>
                                </div>
                                <small class="text-muted d-block">
                                    Keeps this page's settings in the <code>{{ config('cache.default') }}</code>
                                    cache instead of reading them on every request. Cleared automatically
                                    whenever they are saved.
                                </small>

                                <hr>

                                {{-- Off by default and deliberately so: the opening deletes the
                                     day's estimates, angadiya, hisab and cash entries for good. --}}
                                <div class="form-check form-switch">
                                    <input type="hidden" name="auto_opening_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" id="auto_opening_enabled"
                                        name="auto_opening_enabled" value="1"
                                        @checked(old('auto_opening_enabled', $settings->auto_opening_enabled))>
                                    <label class="form-check-label" for="auto_opening_enabled">
                                        Open the day automatically
                                    </label>
                                </div>
                                <small class="text-muted d-block">
                                    Runs the day opening at 11:30 every day, which sends the reports and
                                    then <strong>permanently deletes</strong> the day's estimates, angadiya
                                    slips, hisab and cash entries. Off, it only runs from the
                                    <a href="{{ route('day-opening.show') }}">Day Opening</a> screen.
                                    Either way it needs <code>php artisan schedule:work</code> running.
                                </small>

                                @error('dashboard_sections')
                                    <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                                @enderror
                                @error('dashboard_sections.*')
                                    <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-documents" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Repair Forms</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="repair_ref_prefix" class="form-label">Reference prefix</label>
                                        <input type="text" id="repair_ref_prefix" name="repair_ref_prefix"
                                            class="form-control @error('repair_ref_prefix') is-invalid @enderror"
                                            value="{{ old('repair_ref_prefix', $settings->repair_ref_prefix) }}" maxlength="10"
                                            placeholder="RG">
                                        <small class="text-muted">Prints as <code>{{ $settings->repair_ref_prefix ?: 'RG' }} {{ $settings->repair_next_ref_no }}</code>.</small>
                                        @error('repair_ref_prefix')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="repair_next_ref_no" class="form-label">Next reference no</label>
                                        <input type="number" min="1" id="repair_next_ref_no" name="repair_next_ref_no"
                                            class="form-control @error('repair_next_ref_no') is-invalid @enderror"
                                            value="{{ old('repair_next_ref_no', $settings->repair_next_ref_no) }}">
                                        <small class="text-muted">
                                            Issued automatically. Set it to continue your existing numbering
                                            before the first entry.
                                        </small>
                                        @error('repair_next_ref_no')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="repair_contact_no" class="form-label">Contact number</label>
                                        <input type="text" id="repair_contact_no" name="repair_contact_no"
                                            class="form-control @error('repair_contact_no') is-invalid @enderror"
                                            value="{{ old('repair_contact_no', $settings->repair_contact_no) }}" maxlength="30"
                                            placeholder="9712406367">
                                        <small class="text-muted">
                                            Printed at the top of a repair form. Falls back to the firm phone above.
                                        </small>
                                        @error('repair_contact_no')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-2 mb-3">
                                        <label for="repair_metal_type_id" class="form-label">Default metal</label>
                                        <select id="repair_metal_type_id" name="repair_metal_type_id" class="form-select">
                                            <option value="">None</option>
                                            @foreach ($metalTypes as $id => $name)
                                                <option value="{{ $id }}" @selected(old('repair_metal_type_id', $settings->repair_metal_type_id) == $id)>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2 mb-3">
                                        <label for="repair_purity_id" class="form-label">Default purity</label>
                                        <select id="repair_purity_id" name="repair_purity_id" class="form-select">
                                            <option value="">None</option>
                                            @foreach ($purities as $id => $label)
                                                <option value="{{ $id }}" @selected(old('repair_purity_id', $settings->repair_purity_id) == $id)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label for="repair_terms" class="form-label">Terms and conditions</label>
                                        <textarea id="repair_terms" name="repair_terms" rows="6"
                                            class="form-control @error('repair_terms') is-invalid @enderror">{{ old('repair_terms', $settings->repair_terms) }}</textarea>
                                        <small class="text-muted">One condition per line; printed at the foot of both copies.</small>
                                        @error('repair_terms')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Order Forms</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="order_ref_prefix" class="form-label">Reference prefix</label>
                                        <input type="text" id="order_ref_prefix" name="order_ref_prefix"
                                            class="form-control @error('order_ref_prefix') is-invalid @enderror"
                                            value="{{ old('order_ref_prefix', $settings->order_ref_prefix) }}" maxlength="10"
                                            placeholder="CF">
                                        <small class="text-muted">Prints as <code>{{ $settings->order_ref_prefix ?: 'CF' }} {{ $settings->order_next_ref_no }}</code>.</small>
                                        @error('order_ref_prefix')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="order_next_ref_no" class="form-label">Next reference no</label>
                                        <input type="number" min="1" id="order_next_ref_no" name="order_next_ref_no"
                                            class="form-control @error('order_next_ref_no') is-invalid @enderror"
                                            value="{{ old('order_next_ref_no', $settings->order_next_ref_no) }}">
                                        <small class="text-muted">
                                            Issued automatically. Set it to continue your existing numbering
                                            before the first order.
                                        </small>
                                        @error('order_next_ref_no')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="order_contact_no" class="form-label">Query number</label>
                                        <input type="text" id="order_contact_no" name="order_contact_no"
                                            class="form-control @error('order_contact_no') is-invalid @enderror"
                                            value="{{ old('order_contact_no', $settings->order_contact_no) }}" maxlength="30"
                                            placeholder="9712406367">
                                        <small class="text-muted">
                                            Prints as <em>For Query</em>. Falls back to the firm phone.
                                        </small>
                                        @error('order_contact_no')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label for="order_terms" class="form-label">Terms and conditions</label>
                                        <textarea id="order_terms" name="order_terms" rows="6"
                                            class="form-control @error('order_terms') is-invalid @enderror">{{ old('order_terms', $settings->order_terms) }}</textarea>
                                        <small class="text-muted">One condition per line; printed at the foot of the order form.</small>
                                        @error('order_terms')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Estimates, Vouchers &amp; Cash</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    {{-- A counter each, kept apart on purpose: every
                                         document numbers independently. --}}
                                    @foreach ([
                                        ['key' => 'og_estimate', 'title' => 'OG Estimate', 'placeholder' => 'OG'],
                                        ['key' => 'item_estimate', 'title' => 'Rough Estimate', 'placeholder' => 'ES'],
                                        ['key' => 'voucher', 'title' => 'Voucher', 'placeholder' => 'VC'],
                                        ['key' => 'cash_entry', 'title' => 'Cash Entry', 'placeholder' => 'CS'],
                                    ] as $doc)
                                        @php($prefixField = $doc['key'] . '_ref_prefix')
                                        @php($counterField = $doc['key'] . '_next_ref_no')

                                        <div class="col-lg-4">
                                            <div class="fw-semibold mb-2">{{ $doc['title'] }}</div>

                                            <div class="row">
                                                <div class="col-6 mb-3">
                                                    <label for="{{ $prefixField }}" class="form-label">Prefix</label>
                                                    <input type="text" id="{{ $prefixField }}" name="{{ $prefixField }}"
                                                        class="form-control @error($prefixField) is-invalid @enderror"
                                                        value="{{ old($prefixField, $settings->$prefixField) }}"
                                                        maxlength="10" placeholder="{{ $doc['placeholder'] }}">
                                                    <small class="text-muted">
                                                        Prints as
                                                        <code>{{ trim(($settings->$prefixField ?: '') . ' ' . $settings->$counterField) }}</code>.
                                                        Leave blank for a bare number.
                                                    </small>
                                                    @error($prefixField)
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-6 mb-3">
                                                    <label for="{{ $counterField }}" class="form-label">Next no</label>
                                                    <input type="number" min="1" id="{{ $counterField }}"
                                                        name="{{ $counterField }}"
                                                        class="form-control @error($counterField) is-invalid @enderror"
                                                        value="{{ old($counterField, $settings->$counterField) }}">
                                                    <small class="text-muted">
                                                        Issued automatically. Set it to continue your existing
                                                        numbering before the first entry.
                                                    </small>
                                                    @error($counterField)
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div class="col-md-3 mb-3">
                                        <label for="gst_percent" class="form-label">GST %</label>
                                        <input type="number" step="0.01" min="0" max="100" id="gst_percent"
                                            name="gst_percent"
                                            class="form-control @error('gst_percent') is-invalid @enderror"
                                            value="{{ old('gst_percent', $settings->gst_percent) }}">
                                        <small class="text-muted">
                                            Used when an estimate has GST ticked. Copied onto the estimate as it
                                            is saved, so changing it never re-prices one already given.
                                        </small>
                                        @error('gst_percent')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Supplier Orders</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="supplier_order_next_form_no" class="form-label">Next form no</label>
                                        <input type="number" min="1" id="supplier_order_next_form_no"
                                            name="supplier_order_next_form_no"
                                            class="form-control @error('supplier_order_next_form_no') is-invalid @enderror"
                                            value="{{ old('supplier_order_next_form_no', $settings->supplier_order_next_form_no) }}">
                                        <small class="text-muted">
                                            Issued automatically. Set it to continue your existing numbering
                                            before the first entry.
                                        </small>
                                        @error('supplier_order_next_form_no')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-8 mb-3">
                                        <label for="supplier_order_header" class="form-label">Receipt header</label>
                                        <input type="text" id="supplier_order_header" name="supplier_order_header"
                                            class="form-control @error('supplier_order_header') is-invalid @enderror"
                                            value="{{ old('supplier_order_header', $settings->supplier_order_header) }}"
                                            maxlength="150" placeholder="Firma 012871212">
                                        <small class="text-muted">Printed at the top right of the karigar receipt.</small>
                                        @error('supplier_order_header')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Angadiya Slip Sheet</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="angadiya_columns" class="form-label">Slips per row</label>
                                        <input type="number" min="1" max="6" id="angadiya_columns" name="angadiya_columns"
                                            class="form-control @error('angadiya_columns') is-invalid @enderror"
                                            value="{{ old('angadiya_columns', $settings->angadiya_columns) }}">
                                        <small class="text-muted">Across an A4 portrait page.</small>
                                        @error('angadiya_columns')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="angadiya_slip_height_mm" class="form-label">Slip height (mm)</label>
                                        <input type="number" step="0.01" min="20" max="200" id="angadiya_slip_height_mm"
                                            name="angadiya_slip_height_mm"
                                            class="form-control @error('angadiya_slip_height_mm') is-invalid @enderror"
                                            value="{{ old('angadiya_slip_height_mm', $settings->angadiya_slip_height_mm) }}">
                                        @error('angadiya_slip_height_mm')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="hallmark_next_lot_no" class="form-label">Next hallmark lot no</label>
                                        <input type="number" min="1" id="hallmark_next_lot_no" name="hallmark_next_lot_no"
                                            class="form-control @error('hallmark_next_lot_no') is-invalid @enderror"
                                            value="{{ old('hallmark_next_lot_no', $settings->hallmark_next_lot_no) }}">
                                        <small class="text-muted">
                                            Lot numbers are issued automatically from here. Set it to continue
                                            your existing numbering before the first entry.
                                        </small>
                                        @error('hallmark_next_lot_no')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-landing" role="tabpanel">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Landing Page</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    The public page customers see at
                                    <a href="{{ url('/') }}" target="_blank">{{ url('/') }}</a>.
                                    Off by default; while it is off that address redirects to the
                                    login screen as it always has. Everything on the page is optional
                                    &mdash; a field left blank simply does not appear.
                                </p>

                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="landing_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="landing_enabled" name="landing_enabled" value="1"
                                        @checked(old('landing_enabled', $settings->landing_enabled))>
                                    <label class="form-check-label" for="landing_enabled">
                                        Show the landing page publicly
                                        <small class="text-muted d-block fs-12">
                                            Anyone with the address can see it, including the bank
                                            details and phone numbers below.
                                        </small>
                                    </label>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label d-block">Look</label>
                                    @foreach (App\Models\AppSetting::LANDING_LAYOUTS as $value => [$label, $hint])
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="landing_layout"
                                                id="landing_layout_{{ $value }}" value="{{ $value }}"
                                                @checked(old('landing_layout', $settings->landingLayout()) === $value)>
                                            <label class="form-check-label" for="landing_layout_{{ $value }}">
                                                {{ $label }}
                                                <small class="text-muted d-block fs-12">{{ $hint }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                    @error('landing_layout')
                                        <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="landing_announcement" class="form-label">Announcement</label>
                                    <input type="text" id="landing_announcement" name="landing_announcement"
                                        class="form-control @error('landing_announcement') is-invalid @enderror"
                                        maxlength="255" placeholder="Diwali offer — free polish on all repairs this week"
                                        value="{{ old('landing_announcement', $settings->landing_announcement) }}">
                                    <small class="text-muted">One sentence, shown as a banner. Leave blank for none.</small>
                                    @error('landing_announcement')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="landing_rate_note" class="form-label">Rate Note</label>
                                    <input type="text" id="landing_rate_note" name="landing_rate_note"
                                        class="form-control @error('landing_rate_note') is-invalid @enderror"
                                        maxlength="20" placeholder="+GST"
                                        value="{{ old('landing_rate_note', $settings->landing_rate_note) }}">
                                    <small class="text-muted">Printed beside every rate, e.g. <code>+GST</code>.</small>
                                    @error('landing_rate_note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="landing_phones" class="form-label">Contact Numbers</label>
                                    <textarea id="landing_phones" name="landing_phones" rows="3"
                                        class="form-control @error('landing_phones') is-invalid @enderror"
                                        placeholder="7874655115&#10;07926925755">{{ old('landing_phones', $settings->landing_phones) }}</textarea>
                                    <small class="text-muted">
                                        One per line, shown as tap-to-call buttons. Blank falls back to
                                        the firm phone numbers on the General tab.
                                    </small>
                                    @error('landing_phones')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-0">
                                    <label for="firm_address" class="form-label">Shop Address</label>
                                    <textarea id="firm_address" name="firm_address" rows="2"
                                        class="form-control @error('firm_address') is-invalid @enderror"
                                        placeholder="Shop No.1, Abhishree Complex, Satellite Rd, Ahmedabad 380015">{{ old('firm_address', $settings->firm_address) }}</textarea>
                                    <small class="text-muted">Shown in the page footer.</small>
                                    @error('firm_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Rates to Show</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    Which rates the landing page may publish. A rate appears only when
                                    it is ticked here <em>and</em> entered on
                                    <a href="{{ route('rates.today') }}">Rates &rarr; Today</a> &mdash;
                                    yesterday's figure is never shown as today's.
                                </p>

                                {{-- Present even when every box is unticked, so the server is told
                                     "none" rather than nothing at all. --}}
                                <input type="hidden" name="landing_rate_purities[]" value="">

                                @forelse ($landingPurities as $metal => $group)
                                    <p class="fw-semibold mb-1 mt-2">{{ $metal }}</p>
                                    <div class="row row-cols-1 row-cols-md-2 g-2 mb-2">
                                        @foreach ($group as $purity)
                                            <div class="col">
                                                <div class="form-check">
                                                    <input class="form-check-input landing-rate-pick" type="checkbox"
                                                        name="landing_rate_purities[]" value="{{ $purity->id }}"
                                                        id="landing-purity-{{ $purity->id }}"
                                                        @checked($purity->show_on_landing)>
                                                    <label class="form-check-label" for="landing-purity-{{ $purity->id }}">
                                                        {{ $purity->name }}
                                                        @if (in_array($purity->id, $pricedToday, true))
                                                            <span class="badge bg-success-subtle text-success ms-1">priced today</span>
                                                        @else
                                                            <span class="badge bg-light text-muted ms-1">no rate today</span>
                                                        @endif
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No active purities to choose from.</p>
                                @endforelse

                                <div class="d-flex gap-2 mt-3">
                                    <button type="button" class="btn btn-sm btn-light" id="landing-rates-all">Select all</button>
                                    <button type="button" class="btn btn-sm btn-light" id="landing-rates-none">Select none</button>
                                </div>

                                @error('landing_rate_purities.*')
                                    <div class="text-danger fs-13 mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Social Media</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    Full addresses, including <code>https://</code>. Only the ones you
                                    fill in appear.
                                </p>

                                @foreach (App\Models\AppSetting::SOCIAL_PLATFORMS as $column => [$label, $icon])
                                    <div class="mb-3">
                                        <label for="{{ $column }}" class="form-label">{{ $label }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="{{ $icon }}"></i></span>
                                            <input type="url" id="{{ $column }}" name="{{ $column }}"
                                                class="form-control @error($column) is-invalid @enderror"
                                                maxlength="200" placeholder="https://"
                                                value="{{ old($column, $settings->{$column}) }}">
                                            @error($column)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Bank Details</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    Shown publicly on the landing page. Leave anything you would rather
                                    not publish blank &mdash; it will not be printed.
                                </p>

                                <div class="row g-3">
                                    @foreach (App\Models\AppSetting::BANK_FIELDS as $column => $label)
                                        <div class="col-md-6">
                                            <label for="{{ $column }}" class="form-label">{{ $label }}</label>
                                            <input type="text" id="{{ $column }}" name="{{ $column }}"
                                                class="form-control @error($column) is-invalid @enderror"
                                                maxlength="150" value="{{ old($column, $settings->{$column}) }}">
                                            @error($column)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header py-2">
                                <h5 class="mb-0">Payment QR</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-13">
                                    PNG, JPG, WEBP or SVG, up to 1 MB. Leave empty to hide the QR panel.
                                </p>

                                @php($qrUrl = $settings->paymentQrUrl())

                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <div class="bg-light rounded p-2 text-center" style="min-width: 140px;">
                                        @if ($qrUrl)
                                            <img src="{{ $qrUrl }}" alt="Payment QR" style="max-height: 110px; max-width: 120px;">
                                        @else
                                            <span class="text-muted fs-12">None</span>
                                        @endif
                                    </div>

                                    <div class="flex-grow-1">
                                        <input type="file" id="payment_qr" name="payment_qr"
                                            class="form-control @error('payment_qr') is-invalid @enderror"
                                            accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                        <small class="text-muted">A square image reads best.</small>
                                        @error('payment_qr')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                @if ($qrUrl)
                                    <div class="form-check">
                                        <input type="hidden" name="remove_payment_qr" value="0">
                                        <input class="form-check-input" type="checkbox" id="remove_payment_qr"
                                            name="remove_payment_qr" value="1">
                                        <label class="form-check-label text-danger" for="remove_payment_qr">
                                            Remove this QR code
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        @can('app_setting.edit')
            <div class="mb-4">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Save Appearance
                </button>
            </div>
        @endcan
    </form>
@endsection

@push('js')
    <script>
        $(function () {
            const fields = ['sidebar_user_bg_from', 'sidebar_user_bg_to', 'sidebar_user_text_color'];
            const defaults = { sidebar_user_bg_from: '#0acf97', sidebar_user_bg_to: '#39afd1', sidebar_user_text_color: '#ffffff' };

            function refresh() {
                const from = $('#sidebar_user_bg_from').val();
                const to = $('#sidebar_user_bg_to').val();
                const text = $('#sidebar_user_text_color').val();

                $('#sidebar-preview').css({
                    'background-image': 'linear-gradient(135deg, ' + from + ' 0%, ' + to + ' 100%)',
                    'background-color': from,
                    'color': text
                });
                $('#preview-name, #preview-role').css('color', text);
            }

            fields.forEach(function (field) {
                // The picker and the hex box are two views of one value.
                $('#' + field + '_picker').on('input', function () {
                    $('#' + field).val($(this).val());
                    refresh();
                });

                $('#' + field).on('input', function () {
                    if (/^#[0-9a-fA-F]{6}$/.test($(this).val())) {
                        $('#' + field + '_picker').val($(this).val());
                    }
                    refresh();
                });
            });

            // --- table header preview -----------------------------------------
            // Mirrors AppSetting::readableTextOn(): the WCAG luminance weighting,
            // so the preview agrees with what the server will store.
            function readableTextOn(hex) {
                const r = parseInt(hex.substr(1, 2), 16);
                const g = parseInt(hex.substr(3, 2), 16);
                const b = parseInt(hex.substr(5, 2), 16);

                return ((0.2126 * r + 0.7152 * g + 0.0722 * b) / 255) > 0.55 ? '#212529' : '#ffffff';
            }

            // The shipped defaults from config/appearance.php, so the preview shows
            // what "use the app default" actually looks like.
            const theadDefaults = @js([
                'light' => \App\Models\AppSetting::defaultTableHeaderColours('light'),
                'dark' => \App\Models\AppSetting::defaultTableHeaderColours('dark'),
            ]);

            function refreshThead(mode) {
                const useDefault = $('#table_header_default_' + mode).is(':checked');
                const value = $('#table_header_bg_' + mode).val();
                const $row = $('#thead-preview-' + mode).find('th');

                if (useDefault || ! /^#[0-9a-fA-F]{6}$/.test(value)) {
                    const fallback = theadDefaults[mode];

                    $row.css(fallback
                        ? { 'background-color': fallback.bg, 'color': fallback.text || '' }
                        : { 'background-color': '', 'color': '' });

                    return;
                }

                $row.css({ 'background-color': value, 'color': readableTextOn(value) });
            }

            ['light', 'dark'].forEach(function (mode) {
                const $text = $('#table_header_bg_' + mode);
                const $picker = $('#table_header_bg_' + mode + '_picker');
                const $default = $('#table_header_default_' + mode);

                $picker.on('input', function () {
                    $text.val($(this).val());
                    // Choosing a colour is an explicit choice, so stop using the default.
                    $default.prop('checked', false);
                    refreshThead(mode);
                });

                $text.on('input', function () {
                    if (/^#[0-9a-fA-F]{6}$/.test($(this).val())) {
                        $picker.val($(this).val());
                        $default.prop('checked', false);
                    }
                    refreshThead(mode);
                });

                $default.on('change', function () {
                    if (this.checked) {
                        $text.val('');
                    }
                    refreshThead(mode);
                });

                refreshThead(mode);
            });

            $('#reset-colours').on('click', function () {
                fields.forEach(function (field) {
                    $('#' + field).val(defaults[field]);
                    $('#' + field + '_picker').val(defaults[field]);
                });
                refresh();
            });

            refresh();
        });
    </script>
@endpush

@push('js')
    <script>
        $(function () {
            $('#sections-all').on('click', () => $('.dashboard-pick').prop('checked', true));
            $('#sections-none').on('click', () => $('.dashboard-pick').prop('checked', false));

            $('#landing-rates-all').on('click', () => $('.landing-rate-pick').prop('checked', true));
            $('#landing-rates-none').on('click', () => $('.landing-rate-pick').prop('checked', false));
        });
    </script>
@endpush

@push('js')
    <script>
        $(function () {
            // One form across three panes, so an error in a pane that is not showing
            // would otherwise be invisible — the page would just refuse to save with
            // nothing on screen to explain why. Open whichever pane holds the first one.
            const $failed = $('.tab-pane').find('.is-invalid, .invalid-feedback:visible, .text-danger').first();

            if ($failed.length) {
                const paneId = $failed.closest('.tab-pane').attr('id');

                if (paneId) {
                    bootstrap.Tab.getOrCreateInstance($('a[href="#' + paneId + '"]')[0]).show();
                    $failed.closest('.card')[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
@endpush

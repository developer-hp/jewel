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

        <div class="row">
            <div class="col-lg-7">
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
                                    placeholder="KRSONS">
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
                        </div>
                    </div>
                </div>

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

            <div class="col-lg-5">
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

@csrf
@if ($setting->exists)
    @method('PUT')
@endif

@php
    // Which layouts each switch actually affects. The standard tag ignores the
    // detail-only ones, so showing them there would be a lie.
    $all = 'standard stone_detail diamond_detail';
    $detail = 'stone_detail diamond_detail';

    $fieldFlags = [
        'show_gross' => ['Gross weight (GW)', $all],
        'show_net' => ['Net weight (NW)', $all],
        'show_purity' => ['Purity (PUR)', 'standard stone_detail'],
        'show_huid' => ['HUID', 'standard'],
        'show_stone' => ['Stone rows', $all],
        'show_diamond' => ['Diamond rows', $all],
        'show_stone_rate' => ['Stone / diamond rate column', $detail],
        'show_extra_charges' => ['Extra charges', 'standard stone_detail'],
        'show_oc' => ['Other charges total (OC)', 'stone_detail'],
        'show_making_charge' => ['Making charge (LB)', $detail],
        'show_item_name' => ['Item name', 'stone_detail'],
        'show_shop_name' => ['Shop name', $all],
    ];
@endphp

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Template</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $setting->name) }}" maxlength="60" required
                            placeholder="Jadtar Tag">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-5 mb-3">
                        <label for="layout" class="form-label">Layout <span class="text-danger">*</span></label>
                        <select id="layout" name="layout" class="form-select @error('layout') is-invalid @enderror"
                            required>
                            @foreach (\App\Models\LabelSetting::LAYOUTS as $value => $label)
                                <option value="{{ $value }}" @selected(old('layout', $setting->layout) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('layout')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border mb-0 fs-13" id="layout-note">
                            <i class="ri-information-line me-1"></i>
                            <span data-layout-note="standard">
                                The item code and net weight, then columns of <code>KEY: value</code> pairs.
                                Stones and diamonds are summed into one line each.
                            </span>
                            <span data-layout-note="stone_detail" class="d-none">
                                A row per stone — code, weight, pieces, rate, amount — with the extra
                                charges beneath and <code>OC</code> totalling them.
                                Needs at least {{ \App\Models\LabelSetting::DETAIL_MIN_HEIGHT_MM }} mm of height.
                            </span>
                            <span data-layout-note="diamond_detail" class="d-none">
                                A sieve cell per diamond with <code>DW</code>, <code>DR</code> and the
                                shape as <code>DS</code>.
                                Needs at least {{ \App\Models\LabelSetting::DETAIL_MIN_HEIGHT_MM }} mm of height.
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Tag Size</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="tag_width_mm" class="form-label">Width (mm) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" id="tag_width_mm" name="tag_width_mm"
                            class="form-control @error('tag_width_mm') is-invalid @enderror"
                            value="{{ old('tag_width_mm', $setting->tag_width_mm) }}" required>
                        @error('tag_width_mm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tag_height_mm" class="form-label">Height (mm) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" id="tag_height_mm" name="tag_height_mm"
                            class="form-control @error('tag_height_mm') is-invalid @enderror"
                            value="{{ old('tag_height_mm', $setting->tag_height_mm) }}" required>
                        @error('tag_height_mm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="margin_mm" class="form-label">Margin (mm) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" id="margin_mm" name="margin_mm"
                            class="form-control @error('margin_mm') is-invalid @enderror"
                            value="{{ old('margin_mm', $setting->margin_mm) }}" required>
                        @error('margin_mm')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="font_size_pt" class="form-label">Font Size (pt) <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" id="font_size_pt" name="font_size_pt"
                            class="form-control @error('font_size_pt') is-invalid @enderror"
                            value="{{ old('font_size_pt', $setting->font_size_pt) }}" required>
                        @error('font_size_pt')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3" data-layouts="{{ $detail }}">
                        <label for="max_stone_rows" class="form-label">Max stone rows <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="20" id="max_stone_rows" name="max_stone_rows"
                            class="form-control @error('max_stone_rows') is-invalid @enderror"
                            value="{{ old('max_stone_rows', $setting->max_stone_rows) }}" required>
                        <small class="text-muted">
                            Beyond this the remaining rows collapse into one <code>OTH</code> line, so the
                            tag stays on one page and the amounts still add up.
                        </small>
                        @error('max_stone_rows')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border mb-0 fs-13">
                            <i class="ri-ruler-line me-1"></i>
                            Paper box: <strong id="paper-preview">—</strong>.
                            About <strong id="line-preview">—</strong> lines fit at this height.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">QR Code</h5>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="qr_enabled" value="0">
                    <input class="form-check-input" type="checkbox" id="qr_enabled" name="qr_enabled" value="1"
                        @checked(old('qr_enabled', $setting->qr_enabled))>
                    <label class="form-check-label" for="qr_enabled">Print a QR code on the tag</label>
                </div>

                <div class="row" id="qr-options">
                    <div class="col-md-7 mb-3">
                        <label for="qr_content" class="form-label">QR Contains</label>
                        <select id="qr_content" name="qr_content"
                            class="form-select @error('qr_content') is-invalid @enderror">
                            @foreach (\App\Models\LabelSetting::QR_CONTENTS as $value => $label)
                                <option value="{{ $value }}" @selected(old('qr_content', $setting->qr_content) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('qr_content')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-5 mb-3">
                        <label for="qr_size_mm" class="form-label">QR Size (mm)</label>
                        <input type="number" step="0.01" id="qr_size_mm" name="qr_size_mm"
                            class="form-control @error('qr_size_mm') is-invalid @enderror"
                            value="{{ old('qr_size_mm', $setting->qr_size_mm) }}">
                        @error('qr_size_mm')
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
                <h5 class="mb-0">Visible Fields</h5>
            </div>
            <div class="card-body">
                <p class="text-muted fs-13">
                    A field with no value is skipped even when ticked, so a plain gold piece
                    prints a sparse tag rather than a grid of zeros. Only the switches this
                    layout uses are shown.
                </p>

                @foreach ($fieldFlags as $flag => [$label, $layouts])
                    <div class="form-check form-switch mb-2" data-layouts="{{ $layouts }}">
                        <input type="hidden" name="{{ $flag }}" value="0">
                        <input class="form-check-input" type="checkbox" id="{{ $flag }}"
                            name="{{ $flag }}" value="1" @checked(old($flag, $setting->{$flag}))>
                        <label class="form-check-label" for="{{ $flag }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">Shop Details</h5>
            </div>
            <div class="card-body">
                <label for="shop_name" class="form-label">Shop Name</label>
                <input type="text" id="shop_name" name="shop_name"
                    class="form-control @error('shop_name') is-invalid @enderror"
                    value="{{ old('shop_name', $setting->shop_name) }}" maxlength="60"
                    placeholder="Printed above the item code">
                <small class="text-muted">Only prints when “Shop name” is ticked above.</small>
                @error('shop_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @if ($setting->exists)
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Used By</h5>
                </div>
                <div class="card-body">
                    @if ($setting->is_default)
                        <p class="mb-0">
                            <span class="badge bg-success">Default</span>
                            Every metal type without a template of its own prints with this one.
                        </p>
                    @else
                        @forelse ($setting->metalTypes as $metalType)
                            <span class="badge bg-primary-subtle text-primary me-1">{{ $metalType->name }}</span>
                        @empty
                            <p class="text-muted mb-0 fs-13">
                                No metal type uses this template yet. Choose it under
                                Masters &rsaquo; Metal Types.
                            </p>
                        @endforelse
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<div class="mb-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line"></i> {{ $setting->exists ? 'Save Template' : 'Create Template' }}
    </button>

    @if ($setting->exists && $previewItem)
        @can('item.print')
            {{-- Pinned to this template, not the item's own, so the preview shows
                 what is being edited. --}}
            <a href="{{ route('items.label', [$previewItem, 'template' => $setting->id]) }}" target="_blank"
                class="btn btn-light">
                <i class="ri-eye-line"></i> Preview ({{ $previewItem->code }})
            </a>
        @endcan
    @endif

    <a href="{{ route('label-settings.index') }}" class="btn btn-light ms-auto">Cancel</a>
</div>

@push('js')
    <script>
        $(function () {
            const MM_TO_PT = 2.83465;

            function refresh() {
                const width = parseFloat($('#tag_width_mm').val()) || 0;
                const height = parseFloat($('#tag_height_mm').val()) || 0;
                const margin = parseFloat($('#margin_mm').val()) || 0;
                const font = parseFloat($('#font_size_pt').val()) || 0;
                const layout = $('#layout').val();

                $('#paper-preview').text(
                    width.toFixed(2) + ' × ' + height.toFixed(2) + ' mm' +
                    ' (' + (width * MM_TO_PT).toFixed(2) + ' × ' + (height * MM_TO_PT).toFixed(2) + ' pt)'
                );

                // Usable height in points divided by the line box (~1.25 × font size).
                const usable = (height - 2 * margin) * MM_TO_PT;
                const lines = font > 0 ? Math.max(0, Math.floor(usable / (font * 1.25))) : 0;
                $('#line-preview').text(lines);

                $('#qr-options').toggle($('#qr_enabled').is(':checked'));

                // Hidden, not disabled — a switch keeps its value when you change
                // layout and change back. Disabling would submit them all as off.
                $('[data-layouts]').each(function () {
                    $(this).toggle(String($(this).data('layouts')).split(' ').includes(layout));
                });

                $('[data-layout-note]').addClass('d-none')
                    .filter('[data-layout-note="' + layout + '"]').removeClass('d-none');
            }

            $('#tag_width_mm, #tag_height_mm, #margin_mm, #font_size_pt, #qr_enabled, #layout')
                .on('input change', refresh);

            refresh();
        });
    </script>
@endpush

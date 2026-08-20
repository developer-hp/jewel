@extends('layouts.app')

@section('title', 'Label Settings')

@php
    $fieldFlags = [
        'show_gross' => 'Gross weight (GW)',
        'show_net' => 'Net weight (NW)',
        'show_purity' => 'Purity (PUR)',
        'show_huid' => 'HUID',
        'show_stone' => 'Stone total (ST)',
        'show_diamond' => 'Diamond total (DI)',
        'show_extra_charges' => 'Extra charges',
        'show_shop_name' => 'Shop name',
    ];
@endphp

@section('content')
    <x-page-title title="Label Settings">
        <x-slot:actions>
            @can('item.print')
                @if ($previewItem)
                    <a href="{{ route('items.label', $previewItem) }}" target="_blank" class="btn btn-soft-primary">
                        <i class="ri-eye-line"></i> Preview ({{ $previewItem->code }})
                    </a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-title>

    @unless ($previewItem)
        <div class="alert alert-info">
            <i class="ri-information-line me-1"></i>
            Add an item to preview the tag.
        </div>
    @endunless

    <form method="POST" action="{{ route('label-settings.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-6">
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
                                    value="{{ old('tag_width_mm', $settings->tag_width_mm) }}" required>
                                @error('tag_width_mm')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="tag_height_mm" class="form-label">Height (mm) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" id="tag_height_mm" name="tag_height_mm"
                                    class="form-control @error('tag_height_mm') is-invalid @enderror"
                                    value="{{ old('tag_height_mm', $settings->tag_height_mm) }}" required>
                                @error('tag_height_mm')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="margin_mm" class="form-label">Margin (mm) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" id="margin_mm" name="margin_mm"
                                    class="form-control @error('margin_mm') is-invalid @enderror"
                                    value="{{ old('margin_mm', $settings->margin_mm) }}" required>
                                @error('margin_mm')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="font_size_pt" class="form-label">Font Size (pt) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" id="font_size_pt" name="font_size_pt"
                                    class="form-control @error('font_size_pt') is-invalid @enderror"
                                    value="{{ old('font_size_pt', $settings->font_size_pt) }}" required>
                                @error('font_size_pt')
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
                                @checked(old('qr_enabled', $settings->qr_enabled))>
                            <label class="form-check-label" for="qr_enabled">Print a QR code on the tag</label>
                        </div>

                        <div class="row" id="qr-options">
                            <div class="col-md-7 mb-3">
                                <label for="qr_content" class="form-label">QR Contains</label>
                                <select id="qr_content" name="qr_content"
                                    class="form-select @error('qr_content') is-invalid @enderror">
                                    @foreach (\App\Models\LabelSetting::QR_CONTENTS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('qr_content', $settings->qr_content) === $value)>
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
                                    value="{{ old('qr_size_mm', $settings->qr_size_mm) }}">
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
                            prints a sparse tag rather than a grid of zeros.
                        </p>

                        @foreach ($fieldFlags as $flag => $label)
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="{{ $flag }}" value="0">
                                <input class="form-check-input" type="checkbox" id="{{ $flag }}"
                                    name="{{ $flag }}" value="1" @checked(old($flag, $settings->{$flag}))>
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
                            value="{{ old('shop_name', $settings->shop_name) }}" maxlength="60"
                            placeholder="Printed above the item code">
                        <small class="text-muted">Only prints when “Shop name” is ticked above.</small>
                        @error('shop_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        @can('label_setting.edit')
            <div class="mb-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Save Settings
                </button>
                @if ($previewItem)
                    <a href="{{ route('items.label', $previewItem) }}" target="_blank" class="btn btn-light">
                        Preview Tag
                    </a>
                @endif
            </div>
        @endcan
    </form>
@endsection

@push('js')
    <script>
        $(function () {
            const MM_TO_PT = 2.83465;

            function refresh() {
                const width = parseFloat($('#tag_width_mm').val()) || 0;
                const height = parseFloat($('#tag_height_mm').val()) || 0;
                const margin = parseFloat($('#margin_mm').val()) || 0;
                const font = parseFloat($('#font_size_pt').val()) || 0;

                $('#paper-preview').text(
                    width.toFixed(2) + ' × ' + height.toFixed(2) + ' mm' +
                    ' (' + (width * MM_TO_PT).toFixed(2) + ' × ' + (height * MM_TO_PT).toFixed(2) + ' pt)'
                );

                // Usable height in points divided by the line box (~1.25 × font size).
                const usable = (height - 2 * margin) * MM_TO_PT;
                const lines = font > 0 ? Math.max(0, Math.floor(usable / (font * 1.25))) : 0;
                $('#line-preview').text(lines);

                $('#qr-options').toggle($('#qr_enabled').is(':checked'));
            }

            $('#tag_width_mm, #tag_height_mm, #margin_mm, #font_size_pt, #qr_enabled')
                .on('input change', refresh);

            refresh();
        });
    </script>
@endpush

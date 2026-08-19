@extends('layouts.app')

@section('title', 'Bulk Photo Upload')

@php($result = session('bulkPhotoResult'))

@section('content')
    <x-page-title title="Bulk Photo Upload">
        <x-slot:actions>
            <a href="{{ route('items.index') }}" class="btn btn-light">Back to Items</a>
        </x-slot:actions>
    </x-page-title>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted fs-13">
                        Name each file after the item code it belongs to — <code>RNG0001.jpg</code> attaches to
                        item <code>RNG0001</code>. Matching ignores case. Files that match no item are reported
                        below and nothing is written for them.
                    </p>

                    <form method="POST" action="{{ route('items.photos.bulk.store') }}"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="photos" class="form-label">Photos <span class="text-danger">*</span></label>
                            <input type="file" id="photos" name="photos[]" multiple required
                                class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
                                accept="image/png,image/jpeg,image/webp">
                            <small class="text-muted">
                                JPG, PNG or WEBP, up to 4 MB each and 200 files per batch.
                            </small>
                            @error('photos')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @error('photos.*')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="overwrite_existing" value="0">
                            <input class="form-check-input" type="checkbox" id="overwrite_existing"
                                name="overwrite_existing" value="1" checked>
                            <label class="form-check-label" for="overwrite_existing">
                                Replace photos on items that already have one
                            </label>
                        </div>

                        <div id="selected-summary" class="alert alert-light border d-none fs-13"></div>

                        <button type="submit" class="btn btn-primary">
                            <i class="ri-upload-cloud-2-line"></i> Upload &amp; Attach
                        </button>
                    </form>
                </div>
            </div>

            @if ($result)
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">Last Upload</h5>
                    </div>
                    <div class="card-body">
                        @foreach ([['attached', 'Attached', 'success'], ['replaced', 'Replaced', 'info']] as [$key, $label, $variant])
                            @if (! empty($result[$key]))
                                <div class="mb-3">
                                    <h6 class="text-{{ $variant }}">
                                        {{ $label }} ({{ count($result[$key]) }})
                                    </h6>
                                    @foreach ($result[$key] as $code)
                                        <span class="badge bg-{{ $variant }}-subtle text-{{ $variant }}">{{ $code }}</span>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach

                        @if (! empty($result['skipped']))
                            <h6 class="text-danger">Skipped ({{ count($result['skipped']) }})</h6>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>File</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($result['skipped'] as $row)
                                            <tr>
                                                <td><code>{{ $row['file'] }}</code></td>
                                                <td class="text-muted">{{ $row['reason'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if (empty($result['attached']) && empty($result['replaced']) && empty($result['skipped']))
                            <p class="text-muted mb-0">Nothing was uploaded.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Storage</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        Photos are written to
                        <span class="badge bg-primary-subtle text-primary">{{ $disk }}</span>
                    </p>
                    <p class="text-muted fs-13 mb-0">
                        @can('app_setting.edit')
                            Change this under <a href="{{ route('app-settings.edit') }}">Appearance</a>.
                        @else
                            An administrator can change this under Appearance.
                        @endcan
                        Photos already uploaded keep serving from the disk they were written to.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2">
                    <h5 class="mb-0">Coverage</h5>
                </div>
                <div class="card-body">
                    @php($withPhoto = $totalItems - $itemsWithoutPhoto)
                    @php($percent = $totalItems > 0 ? round(($withPhoto / $totalItems) * 100) : 0)

                    <div class="d-flex justify-content-between mb-1">
                        <span>{{ $withPhoto }} of {{ $totalItems }} items have a photo</span>
                        <strong>{{ $percent }}%</strong>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: {{ $percent }}%"></div>
                    </div>

                    @if ($itemsWithoutPhoto > 0)
                        <p class="text-muted fs-13 mb-0 mt-2">
                            {{ $itemsWithoutPhoto }} still without a photo.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(function () {
            // Show what was picked, since the browser only reports a file count.
            $('#photos').on('change', function () {
                const files = Array.from(this.files || []);
                const box = $('#selected-summary');

                if (! files.length) {
                    box.addClass('d-none').empty();
                    return;
                }

                const codes = files.map(f => f.name.replace(/\.[^.]+$/, '').toUpperCase());
                const shown = codes.slice(0, 20).join(', ');
                const more = codes.length > 20 ? ' and ' + (codes.length - 20) + ' more' : '';

                box.removeClass('d-none').html(
                    '<strong>' + files.length + ' file(s) selected.</strong> Will look for items: ' + shown + more
                );
            });
        });
    </script>
@endpush

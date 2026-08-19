{{--
    Photo panel for the item screens. The upload is its own form, so it cannot sit
    inside the item form — it is posted separately and does not disturb unsaved edits.
--}}
<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Photo</h5>
        @can('item.edit')
            <a href="{{ route('items.photos.bulk') }}" class="btn btn-sm btn-link p-0" title="Bulk upload">
                <i class="ri-upload-cloud-2-line"></i> Bulk
            </a>
        @endcan
    </div>
    <div class="card-body text-center">
        @if ($item->photoUrl())
            <img src="{{ $item->photoUrl() }}" alt="{{ $item->code }}" class="img-fluid rounded mb-2"
                style="max-height: 220px;">
        @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2"
                style="height: 160px;">
                <span class="text-muted"><i class="ri-image-line fs-24 d-block mb-1"></i>No photo</span>
            </div>
        @endif

        @can('item.edit')
            @if ($item->exists)
                <form action="{{ route('items.photo.store', $item) }}" method="POST" enctype="multipart/form-data"
                    class="text-start">
                    @csrf
                    <input type="file" name="photo" class="form-control form-control-sm mb-2"
                        accept="image/png,image/jpeg,image/webp" required
                        onchange="this.form.submit();">
                    <small class="text-muted d-block mb-2">
                        JPG, PNG or WEBP up to 4 MB. Uploading replaces the current photo.
                    </small>
                </form>

                @if ($item->hasPhoto())
                    <form action="{{ route('items.photo.destroy', $item) }}" method="POST"
                        onsubmit="return confirm('Remove the photo from {{ $item->code }}?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-soft-danger w-100">
                            <i class="ri-delete-bin-2-line"></i> Remove Photo
                        </button>
                    </form>
                @endif
            @else
                <p class="text-muted fs-13 mb-0">Save the item first, then add its photo.</p>
            @endif
        @endcan
    </div>
</div>

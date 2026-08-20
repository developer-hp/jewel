{{-- Lot photo. Unlike the item photo card this posts with the lot form itself,
     since a lot is created and photographed in one go. --}}
<div class="card">
    <div class="card-header py-2">
        <h5 class="mb-0">Photo</h5>
    </div>
    <div class="card-body text-center">
        @if ($lot->photoUrl())
            <img src="{{ $lot->photoUrl() }}" alt="{{ $lot->code }}" class="img-fluid rounded mb-2"
                style="max-height: 180px;">
        @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="height: 120px;">
                <span class="text-muted"><i class="ri-image-line fs-24 d-block mb-1"></i>No photo</span>
            </div>
        @endif

        <input type="file" name="photo" class="form-control form-control-sm"
            accept="image/png,image/jpeg,image/webp">
        <small class="text-muted d-block mt-1">Optional. JPG, PNG or WEBP up to 4 MB.</small>
        @error('photo')
            <div class="text-danger fs-13 mt-1">{{ $message }}</div>
        @enderror

        @if ($lot->hasPhoto())
            <div class="form-check mt-2 text-start">
                <input type="hidden" name="remove_photo" value="0">
                <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
                <label class="form-check-label text-danger fs-13" for="remove_photo">Remove current photo</label>
            </div>
        @endif
    </div>
</div>

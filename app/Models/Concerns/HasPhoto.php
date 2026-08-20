<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * A model carrying one optional photo, stored via App\Services\ItemPhotoStore.
 *
 * Requires `photo_path` and `photo_disk` columns. The disk is recorded alongside
 * the path so a photo keeps serving from wherever it was written, even after the
 * media disk setting is switched.
 */
trait HasPhoto
{
    /**
     * Directory on the disk. Override per model.
     */
    public function photoDirectory(): string
    {
        return 'photos';
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    public function photoUrl(): ?string
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        $disk = $this->photo_disk ?: 'public';

        if (! config("filesystems.disks.{$disk}")) {
            return null;
        }

        return Storage::disk($disk)->url($this->photo_path);
    }
}

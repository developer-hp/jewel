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

    /**
     * The photo as a data URI, so dompdf renders it without reaching for the
     * filesystem or the network. Null when there is nothing to show, or when the
     * file has gone missing — a document should still print without its picture.
     */
    public function photoDataUri(): ?string
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        $disk = $this->photo_disk ?: 'public';

        if (! config("filesystems.disks.{$disk}")) {
            return null;
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($this->photo_path)) {
            return null;
        }

        return 'data:'.$storage->mimeType($this->photo_path).';base64,'
            .base64_encode($storage->get($this->photo_path));
    }
}

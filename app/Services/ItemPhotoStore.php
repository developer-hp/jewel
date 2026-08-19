<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Owns where item photos live. Everything that writes or removes a photo goes
 * through here so the disk choice is honoured in exactly one place.
 */
class ItemPhotoStore
{
    public const DIRECTORY = 'items';

    /** Extensions accepted by the bulk uploader and the single upload. */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function disk(): string
    {
        return AppSetting::resolved()->mediaDisk();
    }

    /**
     * Store a photo against an item, removing whatever it replaces.
     */
    public function put(Item $item, UploadedFile $file): void
    {
        $previousPath = $item->photo_path;
        $previousDisk = $item->photo_disk;

        $disk = $this->disk();

        $item->forceFill([
            // Named after the code so a file pulled off S3 is still identifiable.
            'photo_path' => $file->storeAs(
                self::DIRECTORY,
                $item->code.'-'.uniqid().'.'.$file->getClientOriginalExtension(),
                ['disk' => $disk]
            ),
            'photo_disk' => $disk,
        ])->save();

        $this->deleteFile($previousDisk, $previousPath);
    }

    /**
     * Detach and delete an item's photo.
     */
    public function remove(Item $item): void
    {
        $this->deleteFile($item->photo_disk, $item->photo_path);

        $item->forceFill(['photo_path' => null, 'photo_disk' => null])->save();
    }

    private function deleteFile(?string $disk, ?string $path): void
    {
        if (! $path || ! $disk || ! config("filesystems.disks.{$disk}")) {
            return;
        }

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}

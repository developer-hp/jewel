<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Owns where photos live. Everything that writes or removes one goes through here
 * so the disk choice is honoured in exactly one place.
 *
 * Accepts any model using App\Models\Concerns\HasPhoto — items and item lots both do.
 */
class ItemPhotoStore
{
    /** Extensions accepted by the bulk uploader and the single upload. */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function disk(): string
    {
        return AppSetting::resolved()->mediaDisk();
    }

    /**
     * Store a photo against an item, removing whatever it replaces.
     */
    public function put(Model $model, UploadedFile $file): void
    {
        $previousPath = $model->photo_path;
        $previousDisk = $model->photo_disk;

        $disk = $this->disk();

        $model->forceFill([
            // Named after the code so a file pulled off S3 is still identifiable.
            'photo_path' => $file->storeAs(
                $model->photoDirectory(),
                $model->code.'-'.uniqid().'.'.$file->getClientOriginalExtension(),
                ['disk' => $disk]
            ),
            'photo_disk' => $disk,
        ])->save();

        $this->deleteFile($previousDisk, $previousPath);
    }

    /**
     * Detach and delete a model's photo.
     */
    public function remove(Model $model): void
    {
        $this->deleteFile($model->photo_disk, $model->photo_path);

        $model->forceFill(['photo_path' => null, 'photo_disk' => null])->save();
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

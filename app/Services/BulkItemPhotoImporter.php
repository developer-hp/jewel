<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Attaches uploaded photos to items by filename: RNG0001.jpg lands on item RNG0001.
 */
class BulkItemPhotoImporter
{
    public function __construct(private readonly ItemPhotoStore $photos) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array{attached: array<int, string>, replaced: array<int, string>, skipped: array<int, array{file: string, reason: string}>}
     */
    public function import(array $files, bool $overwriteExisting = true): array
    {
        $result = ['attached' => [], 'replaced' => [], 'skipped' => []];

        // One lookup for the whole batch rather than a query per file.
        $codes = collect($files)
            ->map(fn (UploadedFile $file) => $this->codeFrom($file))
            ->filter()
            ->unique();

        $items = Item::whereIn('code', $codes)->get()->keyBy(fn (Item $item) => Str::upper($item->code));

        foreach ($files as $file) {
            $name = $file->getClientOriginalName();
            $code = $this->codeFrom($file);

            if ($code === null) {
                $result['skipped'][] = ['file' => $name, 'reason' => 'Unsupported file type.'];

                continue;
            }

            $item = $items->get($code);

            if (! $item) {
                $result['skipped'][] = ['file' => $name, 'reason' => "No item with code {$code}."];

                continue;
            }

            $hadPhoto = $item->hasPhoto();

            if ($hadPhoto && ! $overwriteExisting) {
                $result['skipped'][] = ['file' => $name, 'reason' => "{$item->code} already has a photo."];

                continue;
            }

            $this->photos->put($item, $file);

            $result[$hadPhoto ? 'replaced' : 'attached'][] = $item->code;
        }

        return $result;
    }

    /**
     * The item code a file claims, from its name minus the extension. Returns null
     * when the extension is not one we accept.
     */
    private function codeFrom(UploadedFile $file): ?string
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        if (! in_array($extension, ItemPhotoStore::EXTENSIONS, true)) {
            return null;
        }

        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return Str::upper(trim($base));
    }
}

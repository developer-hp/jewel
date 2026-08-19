<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\BulkItemPhotoImporter;
use App\Services\ItemPhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class ItemPhotoController extends Controller implements HasMiddleware
{
    /** Matches the extensions the bulk importer accepts. */
    private const IMAGE_RULES = ['image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'];

    public function __construct(
        private readonly ItemPhotoStore $photos,
        private readonly BulkItemPhotoImporter $importer,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:item.edit')];
    }

    public function store(Request $request, Item $item): RedirectResponse
    {
        $request->validate(['photo' => array_merge(['required'], self::IMAGE_RULES)]);

        $this->photos->put($item, $request->file('photo'));

        return back()->with('success', "Photo attached to {$item->code}.");
    }

    public function destroy(Item $item): RedirectResponse
    {
        if (! $item->hasPhoto()) {
            return back()->with('error', "{$item->code} has no photo to remove.");
        }

        $this->photos->remove($item);

        return back()->with('success', "Photo removed from {$item->code}.");
    }

    public function bulk(): View
    {
        return view('items.photos-bulk', [
            'disk' => $this->photos->disk(),
            'itemsWithoutPhoto' => Item::whereNull('photo_path')->count(),
            'totalItems' => Item::count(),
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:200'],
            'photos.*' => array_merge(['required'], self::IMAGE_RULES),
            'overwrite_existing' => ['boolean'],
        ]);

        $result = $this->importer->import(
            $validated['photos'],
            $request->boolean('overwrite_existing'),
        );

        return back()
            ->with('bulkPhotoResult', $result)
            ->with('success', sprintf(
                '%d attached, %d replaced, %d skipped.',
                count($result['attached']),
                count($result['replaced']),
                count($result['skipped']),
            ));
    }
}

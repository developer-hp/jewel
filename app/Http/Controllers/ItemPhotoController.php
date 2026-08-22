<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\BulkItemPhotoImporter;
use App\Services\ItemPhotoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

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
        return [
            // Looking at photos is reading; attaching and removing them is not.
            new Middleware('permission:item.view', only: ['index', 'show', 'raw']),
            new Middleware('permission:item.edit', except: ['index', 'show', 'raw']),
        ];
    }

    /**
     * Every piece that has a picture, by code. The picture itself is not shown
     * here — a page of thumbnails is a page of downloads; the code is enough to
     * find what you are after, and one click opens the real thing.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return $this->data();
        }

        return view('items.photos.index', [
            'total' => Item::whereNotNull('photo_path')->count(),
        ]);
    }

    private function data(): JsonResponse
    {
        $query = Item::query()
            ->select('items.*')
            ->whereNotNull('photo_path')
            ->with(['itemGroup', 'metalType', 'purity']);

        return DataTables::eloquent($query)
            ->editColumn('code', fn (Item $item) => '<code>'.e($item->code).'</code>')
            ->addColumn('group', fn (Item $item) => e($item->itemGroup?->name ?? '—'))
            ->addColumn('metal', fn (Item $item) => view('items.partials.metal-cell', compact('item'))->render())
            ->addColumn('action', fn (Item $item) => '<a href="'.e(route('items.photo.show', $item)).'"'
                .' target="_blank" rel="noopener" class="btn btn-sm btn-primary btn-icon" title="Open the photo">'
                .'<i class="ri-image-line"></i></a>')
            ->filterColumn('code', function ($q, $keyword) {
                $q->where(fn ($sub) => $sub->where('code', 'like', "%{$keyword}%")
                    ->orWhere('huid', 'like', "%{$keyword}%"));
            })
            ->filterColumn('group', fn ($q, $keyword) => $q->whereRelation('itemGroup', 'name', 'like', "%{$keyword}%"))
            ->rawColumns(['code', 'metal', 'action'])
            ->toJson();
    }

    /**
     * One photo on a page of its own, at whatever size it was uploaded.
     */
    public function show(Item $item): View
    {
        abort_unless($item->hasPhoto(), 404);

        return view('items.photos.show', ['item' => $item]);
    }

    /**
     * The file itself, streamed from whichever disk it was written to.
     *
     * Served through here rather than linking Storage::url() so it works whether
     * the disk is public or not, and whether it is local or S3.
     */
    public function raw(Item $item): StreamedResponse
    {
        abort_unless($item->hasPhoto(), 404);

        $disk = Storage::disk($item->photo_disk ?: 'public');

        abort_unless($disk->exists($item->photo_path), 404);

        return $disk->response($item->photo_path);
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

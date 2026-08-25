<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LabelSetting;
use App\Services\ItemLabelBuilder;
use App\Support\PdfDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ItemLabelController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ItemLabelBuilder $builder) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:item.print')];
    }

    /**
     * Stream the tag inline so it opens in the browser's PDF viewer and prints
     * from there — no download step between the clerk and the label printer.
     */
    public function __invoke(Request $request, Item $item): Response
    {
        // ?template= pins a specific one, which is what the settings screen's
        // Preview button uses so it shows the template being edited rather than
        // whatever this item's metal type happens to point at. Gated, so a Sales
        // user cannot probe template ids through it.
        $settings = $request->filled('template') && $request->user()->can('label_setting.view')
            ? LabelSetting::findOrFail($request->integer('template'))
            : LabelSetting::forItem($item);

        return PdfDocument::stream('items.label', $this->builder->build($item, $settings),
            "{$item->code}.pdf", $settings->pdfConfig());
    }
}

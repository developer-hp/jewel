<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LabelSetting;
use App\Services\ItemLabelBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
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
    public function __invoke(Item $item): Response
    {
        $settings = LabelSetting::current();

        return Pdf::loadView('items.label', $this->builder->build($item, $settings))
            ->setPaper($settings->paperBox())
            ->stream("{$item->code}.pdf", ['Attachment' => false]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Hallmark;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The hallmarking docket.
 *
 * A plain GET, unlike the angadiya sheet, because printing records nothing.
 */
class HallmarkPrintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:hallmark.print')];
    }

    public function __invoke(Hallmark $hallmark): Response
    {
        $hallmark->load(['lines.itemGroup', 'lines.purity', 'lines.supplier']);

        return Pdf::loadView('hallmarks.docket', [
            'hallmark' => $hallmark,
            'photo' => $hallmark->photoDataUri(),
        ])
            ->setPaper('a4', 'portrait')
            ->stream("hallmark-{$hallmark->lot_no}.pdf", ['Attachment' => false]);
    }
}

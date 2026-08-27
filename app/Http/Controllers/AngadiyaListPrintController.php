<?php

namespace App\Http\Controllers;

use App\Models\Angadiya;
use App\Support\PdfDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The despatch list: one line per slip, for whoever hands the parcels over.
 *
 * Deliberately does not stamp printed_at, unlike the slip sheet. What travels with
 * the goods is the slip; this is a manifest, and printing it again to check
 * something off should not look like the slips were reprinted.
 */
class AngadiyaListPrintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:angadiya.print')];
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {

        // Ordered by id, so the list reads in the order they were entered — the same
        // order the slip sheet uses.
        if($request->ids)
        $slips = Angadiya::whereIn('id', $request->ids)->orderBy('id')->get();
        else
        $slips = Angadiya::orderBy('id')->get();

        if ($slips->isEmpty()) {
            return back()->with('error', 'Those slips no longer exist.');
        }

        return PdfDocument::stream('angadiyas.list', [
            'slips' => $slips,
        ], 'angadiya-list-'.now()->format('Y-m-d-His').'.pdf', PdfDocument::a4());
    }
}

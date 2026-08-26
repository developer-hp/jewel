<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\WhatsAppReceiver;
use App\Models\WhatsAppTemplate;
use App\Services\DayOpening;
use App\Services\OpeningReports;
use App\Support\WhatsAppEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * The day opening, run by hand.
 *
 * Gated on app_setting.edit rather than on anything to do with items or cash: this
 * deletes the day's documents for good, and that is an owner's decision.
 */
class DayOpeningController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DayOpening $opening,
        private readonly OpeningReports $reports,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:app_setting.view', only: ['show']),
            new Middleware('permission:app_setting.edit', only: ['run']),
        ];
    }

    public function show(): View
    {
        $since = $this->opening->since();
        $until = now();

        return view('day-opening.show', [
            'settings' => AppSetting::current(),
            'since' => $since,
            'until' => $until,
            // What an opening would report right now, so the button is never a
            // leap in the dark.
            'soldCount' => $this->reports->soldItems($since, $until)->count(),
            'addedCount' => $this->reports->addedItems($since, $until)->count(),
            'receivers' => WhatsAppReceiver::active()->ordered()->get(),
            'templateReady' => WhatsAppTemplate::activeFor(WhatsAppEvent::DocumentSent) !== null,
        ]);
    }

    public function run(): RedirectResponse
    {
        $summary = $this->opening->run();

        return redirect()->route('day-opening.show')->with('success', sprintf(
            'Day opened. %d items marked sold, %d drawers carried forward, %d messages queued.',
            $summary['marked_sold'],
            $summary['drawers'],
            $summary['sent_to'],
        ));
    }
}

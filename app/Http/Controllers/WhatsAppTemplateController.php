<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsAppTemplateRequest;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * What the shop registered with Meta for each message the app can send.
 *
 * Backed by the same permissions as the other settings screens, and kept on its own
 * so outbound messaging is not muddled into branding — the reasoning
 * SecuritySettingController already set out.
 *
 * The listing is driven by the WhatsAppEvent enum rather than by the table, so an
 * event nobody has set up still appears, and a row for an event that no longer
 * exists cannot strand itself out of sight.
 */
class WhatsAppTemplateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:app_setting.view', only: ['index', 'edit']),
            new Middleware('permission:app_setting.edit', only: ['update']),
        ];
    }

    public function index(): View
    {
        $configured = WhatsAppTemplate::all()->keyBy(fn (WhatsAppTemplate $t) => $t->event->value);

        return view('whatsapp-templates.index', [
            'events' => WhatsAppEvent::all(),
            'templates' => $configured,
            'credentialsConfigured' => WhatsAppTemplate::credentialsConfigured(),
        ]);
    }

    public function edit(string $event): View
    {
        $case = $this->event($event);

        return view('whatsapp-templates.edit', [
            'event' => $case,
            // Created blank on first sight, so the form always has a row to bind to.
            'template' => WhatsAppTemplate::forEvent($case),
            'credentialsConfigured' => WhatsAppTemplate::credentialsConfigured(),
        ]);
    }

    public function update(WhatsAppTemplateRequest $request, string $event): RedirectResponse
    {
        $case = $this->event($event);

        WhatsAppTemplate::forEvent($case)->update($request->validated());

        return redirect()->route('whatsapp-templates.index')
            ->with('success', "The {$case->label()} message has been saved.");
    }

    /**
     * The enum case behind a route segment, or a 404. An event is a code concept, so
     * a URL naming one that does not exist is not found rather than a validation
     * error.
     */
    private function event(string $event): WhatsAppEvent
    {
        return WhatsAppEvent::tryFrom($event) ?? abort(404);
    }
}

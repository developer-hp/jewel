<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsAppDocumentRequest;
use App\Models\Customer;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppDocumentStore;
use App\Services\WhatsAppNotifier;
use App\Support\PhoneNumber;
use App\Support\WhatsAppEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Sending a PDF to a customer by hand — a ledger, an invoice, whatever is to hand.
 *
 * The other two messages fire off a saved record. This one has a person standing in
 * front of it, so it validates properly and says what happened rather than logging
 * quietly.
 */
class WhatsAppDocumentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly WhatsAppDocumentStore $documents,
        private readonly WhatsAppNotifier $whatsapp,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:app_setting.view', only: ['create']),
            new Middleware('permission:app_setting.edit', only: ['send']),
        ];
    }

    public function create(): View
    {
        return view('whatsapp-documents.create', [
            'event' => WhatsAppEvent::DocumentSent,
            'template' => WhatsAppTemplate::query()
                ->where('event', WhatsAppEvent::DocumentSent->value)->first(),
            'credentialsConfigured' => WhatsAppTemplate::credentialsConfigured(),
        ]);
    }

    public function send(WhatsAppDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = filled($data['customer_id'] ?? null) ? Customer::find($data['customer_id']) : null;

        // A chosen customer wins; a typed number is the fallback for someone not on
        // the register yet.
        $contactNo = $customer?->phone ?: ($data['contact_no'] ?? '');
        $name = $customer?->name ?: ($data['customer_name'] ?? '');

        if (! WhatsAppTemplate::activeFor(WhatsAppEvent::DocumentSent)) {
            return back()->withInput()->with('error',
                'The Document sent message is not switched on. Set it up under Settings › WhatsApp.');
        }

        if (PhoneNumber::toE164($contactNo, (string) config('services.whatsapp.country_code', '91')) === null) {
            return back()->withInput()->with('error',
                "\"{$contactNo}\" is not a number this can send to.");
        }

        // Stored before dispatching, because the queued job only carries the link —
        // Meta fetches the file itself, later.
        $document = $this->documents->put($request->file('document'));

        $this->whatsapp->documentSent($contactNo, $name, $data['described_as'], $document);

        return redirect()->route('whatsapp-documents.create')
            ->with('success', "The {$data['described_as']} is queued for {$contactNo}.");
    }
}

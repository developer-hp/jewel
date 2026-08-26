<?php

namespace App\Services;

use App\Jobs\SendWhatsAppTemplate;
use App\Models\OrderForm;
use App\Models\RepairForm;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\DocumentSentMessage;
use App\Services\WhatsApp\OrderCreatedMessage;
use App\Services\WhatsApp\RepairCreatedMessage;
use App\Services\WhatsApp\TemplateMessage;
use App\Support\PhoneNumber;
use App\Support\WhatsAppEvent;
use Illuminate\Support\Facades\Log;

/**
 * Who gets told what, and when.
 *
 * Every method here is called after the thing it announces has been committed, and
 * none of them may fail loudly: the record is already saved and the clerk is already
 * being redirected. A messaging problem is a log line, never a 500.
 *
 * A new event is a method here plus a message class under App\Services\WhatsApp —
 * the deciding of whether to send, the normalising and the dispatching are shared.
 */
class WhatsAppNotifier
{
    public function orderCreated(OrderForm $form): void
    {
        $this->send(
            WhatsAppEvent::OrderCreated,
            $form->contact_no,
            new OrderCreatedMessage($form),
            $form->reference(),
        );
    }

    public function repairCreated(RepairForm $form): void
    {
        // The names are read while building the message, so make sure they are here
        // rather than firing a query per row from inside the builder.
        $form->loadMissing('salesPersons');

        $this->send(
            WhatsAppEvent::RepairCreated,
            $form->contact_no,
            new RepairCreatedMessage($form),
            $form->reference(),
        );
    }

    /**
     * A PDF, sent by hand from the Send Document screen.
     *
     * Unlike the other two this is not tied to a record, so it reports back: the
     * clerk is standing at the screen and needs to know whether it went.
     *
     * @param  array{link: string, filename: string}  $document
     */
    public function documentSent(string $contactNo, string $customerName, string $describedAs, array $document): bool
    {
        $template = WhatsAppTemplate::activeFor(WhatsAppEvent::DocumentSent);

        if (! $template) {
            return false;
        }

        $to = PhoneNumber::toE164($contactNo, $this->countryCode());

        if ($to === null) {
            return false;
        }

        $message = new DocumentSentMessage($customerName, $describedAs);

        SendWhatsAppTemplate::dispatch(
            $to,
            $template->name,
            $template->language,
            $message->header(),
            $message->body(),
            $document,
        );

        return true;
    }

    /**
     * Queue one message, or work out why there is nothing to queue.
     */
    private function send(
        WhatsAppEvent $event,
        ?string $contactNo,
        TemplateMessage $message,
        string $reference,
    ): void {
        // Even a dead jobs table cannot be allowed to undo a saved record.
        rescue(function () use ($event, $contactNo, $message, $reference) {
            $template = WhatsAppTemplate::activeFor($event);

            if (! $template) {
                return;
            }

            $to = PhoneNumber::toE164($contactNo, $this->countryCode());

            if ($to === null) {
                // Not an error: a landline, a half-typed number, or a blank. Worth
                // knowing about, not worth a failed job.
                Log::info('WhatsApp: contact number is not sendable.', [
                    'event' => $event->value,
                    'reference' => $reference,
                ]);

                return;
            }

            SendWhatsAppTemplate::dispatch(
                $to,
                $template->name,
                $template->language,
                $message->header(),
                $message->body(),
            );
        }, report: true);
    }

    private function countryCode(): string
    {
        return (string) config('services.whatsapp.country_code', '91');
    }
}

<?php

namespace App\Models;

use App\Support\WhatsAppEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The Meta template the shop registered for one event.
 *
 * Which values fill its placeholders is not here — see App\Services\WhatsApp. This
 * row records only the name, the language and whether it is switched on.
 */
#[Fillable(['event', 'name', 'language', 'is_active'])]
class WhatsAppTemplate extends Model
{
    /**
     * Spelt out because the pluraliser splits the class name at the capital A and
     * comes up with "whats_app_templates".
     */
    protected $table = 'whatsapp_templates';

    protected function casts(): array
    {
        return [
            'event' => WhatsAppEvent::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The template to send for an event, or null when it is not set up, switched
     * off, or has nothing behind it to send with.
     */
    public static function activeFor(WhatsAppEvent $event): ?self
    {
        $template = static::query()->where('event', $event->value)->first();

        return $template?->isSendable() ? $template : null;
    }

    /**
     * The row for an event, created blank on first sight so the settings screen has
     * something to edit. Never active on creation.
     */
    public static function forEvent(WhatsAppEvent $event): self
    {
        return static::firstOrCreate(
            ['event' => $event->value],
            ['name' => '', 'language' => 'en', 'is_active' => false],
        );
    }

    /**
     * Switched on, named, and with credentials behind it.
     *
     * All three in one place, so "off", "not set up" and "no token in .env" cannot
     * drift apart into three different answers.
     */
    public function isSendable(): bool
    {
        return $this->is_active
            && filled($this->name)
            && filled(config('services.whatsapp.token'))
            && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * Whether the .env credentials are present at all — what the settings screen
     * warns about, separately from any one template being off.
     */
    public static function credentialsConfigured(): bool
    {
        return filled(config('services.whatsapp.token'))
            && filled(config('services.whatsapp.phone_number_id'));
    }
}

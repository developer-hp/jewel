<?php

namespace App\Models\Concerns;

use App\Models\OrderForm;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Order Number control shared by the OG estimate and the voucher: "IN", "OUT", or
 * a particular order form.
 *
 * Requires `direction` and `order_form_id` columns. Exactly one is ever set — it is one
 * control on screen, and letting both be filled would leave no answer to what the
 * document is actually against.
 */
trait HasOrderReference
{
    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const DIRECTIONS = [
        self::DIRECTION_IN => 'IN',
        self::DIRECTION_OUT => 'OUT',
    ];

    public function orderForm(): BelongsTo
    {
        return $this->belongsTo(OrderForm::class);
    }

    /**
     * An amount booked against a particular order is an advance against it.
     */
    public function isAgainstOrder(): bool
    {
        return $this->order_form_id !== null;
    }

    /**
     * What the Order Number column shows: the order's reference, or IN / OUT.
     */
    public function orderReferenceLabel(): string
    {
        if ($this->isAgainstOrder()) {
            return $this->orderForm?->reference() ?? '—';
        }

        return self::DIRECTIONS[$this->direction] ?? '—';
    }

    /**
     * What the single select posts, so the form can round-trip the current value.
     */
    public function orderReferenceValue(): string
    {
        return $this->isAgainstOrder() ? 'order:'.$this->order_form_id : (string) $this->direction;
    }

    /**
     * Split what that select posted back into the two columns.
     *
     * @return array{direction: string|null, order_form_id: int|null}
     */
    public static function splitOrderReference(?string $value): array
    {
        if ($value !== null && str_starts_with($value, 'order:')) {
            return ['direction' => null, 'order_form_id' => (int) substr($value, 6)];
        }

        return [
            'direction' => array_key_exists((string) $value, self::DIRECTIONS) ? $value : null,
            'order_form_id' => null,
        ];
    }
}

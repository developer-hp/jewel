<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One movement of gold into or out of an internal stock.
 */
#[Fillable(['internal_stock_id', 'type', 'weight', 'note'])]
class InternalStockEntry extends Model
{
    use SoftDeletes;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_OPENING = 'opening';

    /**
     * The two that add to a balance. Opening is kept apart from `in` so the line
     * that started a pot off stays identifiable.
     */
    public const INCOMING = [self::TYPE_IN, self::TYPE_OPENING];

    public const TYPES = [
        self::TYPE_IN => 'In',
        self::TYPE_OUT => 'Out',
        self::TYPE_OPENING => 'Opening',
    ];

    protected function casts(): array
    {
        return ['weight' => 'decimal:3'];
    }

    public function internalStock(): BelongsTo
    {
        return $this->belongsTo(InternalStock::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOutgoing(): bool
    {
        return $this->type === self::TYPE_OUT;
    }

    /**
     * What this entry does to the balance: positive in, negative out.
     */
    public function signedWeight(): float
    {
        return round((float) $this->weight * ($this->isOutgoing() ? -1 : 1), 3);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function scopeIncoming(Builder $query): void
    {
        $query->whereIn('type', self::INCOMING);
    }

    public function scopeOutgoing(Builder $query): void
    {
        $query->where('type', self::TYPE_OUT);
    }
}

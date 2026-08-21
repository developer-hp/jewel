<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stone or diamond the customer asked for, recorded before any piece exists.
 *
 * The same shape as ItemStone, and snapshotted the same way, so the two can be
 * copied back and forth without translation.
 */
#[Fillable([
    'order_form_line_id', 'stone_master_id', 'kind', 'pieces', 'weight_carat',
    'weight_grams', 'rate_unit', 'rate', 'amount', 'deduct_from_gross',
])]
class OrderFormLineStone extends Model
{
    protected function casts(): array
    {
        return [
            'pieces' => 'integer',
            'weight_carat' => 'decimal:3',
            'weight_grams' => 'decimal:4',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'deduct_from_gross' => 'boolean',
        ];
    }

    public function orderFormLine(): BelongsTo
    {
        return $this->belongsTo(OrderFormLine::class);
    }

    public function stoneMaster(): BelongsTo
    {
        return $this->belongsTo(StoneMaster::class);
    }

    public function isDiamond(): bool
    {
        return $this->kind === StoneMaster::KIND_DIAMOND;
    }
}

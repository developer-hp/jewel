<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stone or diamond on a quoted line, frozen at the moment of quoting.
 */
#[Fillable([
    'stone_master_id', 'kind', 'pieces', 'weight_carat', 'weight_grams',
    'rate_unit', 'rate', 'amount', 'deduct_from_gross',
])]
class ItemEstimateLineStone extends Model
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

    public function line(): BelongsTo
    {
        return $this->belongsTo(ItemEstimateLine::class, 'item_estimate_line_id');
    }

    public function stoneMaster(): BelongsTo
    {
        return $this->belongsTo(StoneMaster::class);
    }
}

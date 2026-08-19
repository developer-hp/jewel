<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'item_id', 'stone_master_id', 'kind', 'pieces', 'weight_carat',
    'weight_grams', 'rate_unit', 'rate', 'amount', 'deduct_from_gross',
])]
class ItemStone extends Model
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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

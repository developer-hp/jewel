<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One group's quota within a lot: how many physical pieces arrived, and how many
 * item records (tags) are to be created for them.
 */
#[Fillable(['item_lot_id', 'item_group_id', 'pieces', 'tags'])]
class ItemLotLine extends Model
{
    protected function casts(): array
    {
        return [
            'pieces' => 'integer',
            'tags' => 'integer',
        ];
    }

    public function itemLot(): BelongsTo
    {
        return $this->belongsTo(ItemLot::class);
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class);
    }
}

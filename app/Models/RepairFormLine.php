<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One piece taken in for repair: the PARTICULARS and ARTICLE WEIGHT columns.
 *
 * The line is ready once the repaired piece has come back and been booked into
 * stock — that is, once an item points at it.
 */
#[Fillable(['description', 'net_weight', 'sort_order'])]
class RepairFormLine extends Model
{
    protected function casts(): array
    {
        return [
            'net_weight' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    public function repairForm(): BelongsTo
    {
        return $this->belongsTo(RepairForm::class);
    }

    /**
     * The stock item created when this piece came back. Null while it is still out.
     */
    public function item(): HasOne
    {
        return $this->hasOne(Item::class);
    }

    public function isReady(): bool
    {
        return $this->item !== null;
    }
}

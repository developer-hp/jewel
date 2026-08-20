<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a hallmark docket.
 *
 * `description` prints under PARTICULARS and `supplier->short_name` under SC.
 */
#[Fillable([
    'item_group_id', 'description', 'purity_id', 'supplier_id',
    'quantity', 'pcs_per_quantity', 'sort_order',
])]
class HallmarkLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'pcs_per_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function hallmark(): BelongsTo
    {
        return $this->belongsTo(Hallmark::class);
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    /**
     * The SC column.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Pieces this line accounts for. Derived, never stored.
     */
    public function totalPcs(): int
    {
        return $this->quantity * $this->pcs_per_quantity;
    }

    /**
     * What prints in the SC cell; blank when the vendor is not yet known.
     */
    public function scCode(): string
    {
        return (string) ($this->supplier?->short_name ?: '');
    }
}

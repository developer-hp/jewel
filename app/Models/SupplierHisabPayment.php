<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of scrap handed over against a hisab: what it was, what it weighed, and
 * at what touch. The fine weight is derived, never stored.
 */
#[Fillable(['item_name', 'gross_weight', 'touch', 'sort_order'])]
class SupplierHisabPayment extends Model
{
    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'touch' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    public function supplierHisab(): BelongsTo
    {
        return $this->belongsTo(SupplierHisab::class);
    }

    /**
     * Pure gold in this line. Touch is a percentage, so 10 g at 91.6 gives 9.160.
     */
    public function fineWeight(): float
    {
        return round((float) $this->gross_weight * (float) $this->touch / 100, 3);
    }
}

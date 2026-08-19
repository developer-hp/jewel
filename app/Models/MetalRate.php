<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purity_id', 'effective_date', 'rate', 'per_grams', 'rate_per_gram', 'created_by'])]
class MetalRate extends Model
{
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'rate' => 'decimal:2',
            'per_grams' => 'decimal:3',
            'rate_per_gram' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        // The per-gram figure is always derived, never accepted from a request.
        static::saving(function (MetalRate $rate) {
            $basis = (float) $rate->per_grams;
            $rate->rate_per_gram = $basis > 0 ? round((float) $rate->rate / $basis, 4) : 0;
        });
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

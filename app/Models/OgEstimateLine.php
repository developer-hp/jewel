<?php

namespace App\Models;

use App\Services\EstimateLineMath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of old gold: what it weighs, what it tests at, and what it is worth.
 */
#[Fillable(['description', 'gross_weight', 'net_weight', 'touch_percent', 'rate', 'sort_order'])]
class OgEstimateLine extends Model
{
    protected $attributes = [
        'gross_weight' => 0,
        'net_weight' => 0,
        'touch_percent' => 0,
        'rate' => 0,
    ];

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'touch_percent' => 'decimal:3',
            'rate' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function ogEstimate(): BelongsTo
    {
        return $this->belongsTo(OgEstimate::class);
    }

    /**
     * Pure gold in this piece. Derived, never stored.
     */
    public function fineWeight(): float
    {
        return app(EstimateLineMath::class)
            ->fineWeight((float) $this->net_weight, (float) $this->touch_percent);
    }

    public function value(): float
    {
        return app(EstimateLineMath::class)
            ->lineValue((float) $this->net_weight, (float) $this->touch_percent, (float) $this->rate);
    }
}

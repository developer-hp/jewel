<?php

namespace App\Models;

use App\Services\ItemEstimateMath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One piece being quoted: gold, jadtar, labour and other charges.
 */
#[Fillable([
    'item_id', 'description', 'gross_weight', 'rate',
    'labour_amount', 'labour_type', 'oc_amount', 'sort_order',
])]
class ItemEstimateLine extends Model
{
    /** The three the Making Charge master has, so labour maps straight across. */
    public const LABOUR_PERCENTAGE = 'percentage';

    public const LABOUR_PER_GRAM = 'per_gram';

    public const LABOUR_FIXED = 'fixed';

    /** Short labels, as they read in the dropdown beside the amount. */
    public const LABOUR_TYPES = [
        self::LABOUR_PERCENTAGE => '%',
        self::LABOUR_PER_GRAM => 'G',
        self::LABOUR_FIXED => 'F',
    ];

    protected $attributes = [
        'gross_weight' => 0,
        'rate' => 0,
        'labour_amount' => 0,
        'labour_type' => self::LABOUR_PER_GRAM,
        'oc_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'rate' => 'decimal:2',
            'labour_amount' => 'decimal:2',
            'oc_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function itemEstimate(): BelongsTo
    {
        return $this->belongsTo(ItemEstimate::class);
    }

    /**
     * The stock piece being quoted. Null on a line typed by description alone.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function stones(): HasMany
    {
        return $this->hasMany(ItemEstimateLineStone::class);
    }

    // --- everything derived, through the one service -------------------------

    private function math(): ItemEstimateMath
    {
        return app(ItemEstimateMath::class);
    }

    public function netWeight(): float
    {
        return $this->math()->netWeight($this);
    }

    public function stoneWeight(): float
    {
        return $this->math()->stoneWeight($this);
    }

    public function jadtar(): float
    {
        return $this->math()->jadtar($this);
    }

    public function metalValue(): float
    {
        return $this->math()->metalValue($this);
    }

    public function labour(): float
    {
        return $this->math()->labour($this);
    }

    /**
     * Stones and diamonds plus other charges — what the printed breakdown lists.
     */
    public function charges(): float
    {
        return $this->math()->charges($this);
    }

    public function total(): float
    {
        return $this->math()->lineTotal($this);
    }

    /**
     * What prints under LC: the amount, and for a per-gram rate what that comes to as
     * a share of the gold rate — "2791(21.00%)" on the paper original.
     */
    public function labourLabel(): string
    {
        $amount = (float) $this->labour_amount;

        if ($amount <= 0) {
            return '';
        }

        if ($this->labour_type === self::LABOUR_PERCENTAGE) {
            return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.').'%';
        }

        if ($this->labour_type === self::LABOUR_FIXED) {
            return number_format($amount, 0);
        }

        $ratePerGram = (float) $this->rate / 10;
        $share = $ratePerGram > 0 ? $amount / $ratePerGram * 100 : 0;

        // No thousands separator on the rate itself, as on the paper original.
        return number_format($amount, 0, '.', '')
            .($share > 0 ? '('.number_format($share, 2).'%)' : '');
    }
}

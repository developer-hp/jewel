<?php

namespace App\Models;

use App\Models\Concerns\HasPhoto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One piece on an order.
 *
 * Either a piece already in the case, promised to this customer, or one to be made —
 * possibly "like that one, but a different size". Ready means a stock item points at
 * it, which is also what reserves that piece.
 */
#[Fillable([
    'source_item_id', 'made_to_order', 'description', 'size_pcs',
    'metal_type_id', 'purity_id', 'net_weight',
    'lc_amount', 'lc_type', 'oc_amount', 'sort_order',
])]
class OrderFormLine extends Model
{
    use HasPhoto;

    public const LC_PER_GRAM = 'per_gram';

    public const LC_PERCENTAGE = 'percentage';

    public const LC_FIXED = 'fixed';

    public const LC_TYPES = [
        self::LC_PER_GRAM => 'Per Gram',
        self::LC_PERCENTAGE => '% of Metal Value',
        self::LC_FIXED => 'Fixed Amount',
    ];

    protected $attributes = [
        'made_to_order' => false,
        'lc_amount' => 0,
        'lc_type' => self::LC_PER_GRAM,
        'oc_amount' => 0,
        'net_weight' => 0,
    ];

    protected function casts(): array
    {
        return [
            'made_to_order' => 'boolean',
            'net_weight' => 'decimal:3',
            'lc_amount' => 'decimal:2',
            'oc_amount' => 'decimal:2',
            'fixed_rate_per_gram' => 'decimal:4',
            'rate_fixed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function photoDirectory(): string
    {
        return 'order-form-lines';
    }

    public function orderForm(): BelongsTo
    {
        return $this->belongsTo(OrderForm::class);
    }

    /**
     * The piece picked from stock — the one being promised, or the one this line is
     * copied from when it is made to order.
     */
    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'source_item_id');
    }

    /**
     * The piece held against this line. Its existence is both "ready" and the
     * reservation; the unique index on items.order_form_line_id enforces the latter.
     */
    public function item(): HasOne
    {
        return $this->hasOne(Item::class);
    }

    public function stones(): HasMany
    {
        return $this->hasMany(OrderFormLineStone::class);
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function isReady(): bool
    {
        return $this->item !== null;
    }

    // --- the pinned rate -----------------------------------------------------

    public function isRateFixed(): bool
    {
        return $this->fixed_rate_per_gram !== null;
    }

    /**
     * Pin the day's rate for this line's purity.
     *
     * Not gated on a piece being held: the rate is what the customer agreed on the
     * day, and a piece made six weeks later must still price at it. Returns false
     * when that purity has no rate entered yet.
     */
    public function fixRate(): bool
    {
        $rate = $this->purity?->ratePerGramOn();

        if ($rate === null) {
            return false;
        }

        $this->forceFill([
            'fixed_rate_per_gram' => $rate,
            'rate_fixed_at' => now(),
        ])->save();

        return true;
    }

    public function releaseRate(): void
    {
        $this->forceFill(['fixed_rate_per_gram' => null, 'rate_fixed_at' => null])->save();
    }

    /**
     * What prints in the Rate column.
     */
    public function rateLabel(): string
    {
        return $this->isRateFixed()
            ? number_format((float) $this->fixed_rate_per_gram, 2)
            : 'Open';
    }

    /**
     * What the stones and diamonds on this line come to.
     *
     * The form adds the chosen piece's extra charges to this and drops the total into
     * the Other Charges box as a starting figure; what is stored is whatever the
     * counter left there.
     */
    public function stoneCharge(): float
    {
        return round((float) $this->stones->sum('amount'), 2);
    }

    /**
     * Labour copied off a piece's making charge. The two vocabularies line up, so
     * the charge type maps straight across.
     */
    public function applyMakingCharge(?MakingCharge $charge): void
    {
        if (! $charge) {
            return;
        }

        $this->lc_amount = (float) $charge->rate;
        $this->lc_type = match ($charge->charge_type) {
            MakingCharge::TYPE_PERCENTAGE => self::LC_PERCENTAGE,
            MakingCharge::TYPE_FIXED => self::LC_FIXED,
            default => self::LC_PER_GRAM,
        };
    }

    /**
     * What prints under "Labour per gm" — the amount read through its type.
     */
    public function labourLabel(): string
    {
        $amount = (float) $this->lc_amount;

        if ($amount <= 0) {
            return '';
        }

        return match ($this->lc_type) {
            self::LC_PERCENTAGE => rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.').'%',
            self::LC_FIXED => number_format($amount, 0),
            default => number_format($amount, 0).'/gm',
        };
    }

    /**
     * Gross weight for a piece made to this line: the ordered net plus whatever the
     * stones and diamonds deduct. ItemCalculator then derives the same net back.
     */
    public function grossFromStones(): float
    {
        $deducted = (float) $this->stones->where('deduct_from_gross', true)->sum('weight_grams');

        return round((float) $this->net_weight + $deducted, 3);
    }
}

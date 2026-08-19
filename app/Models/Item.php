<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One unique physical piece: its own code, its own weights, quantity always 1.
 *
 * The weight columns below `gross_weight` are derived by App\Services\ItemCalculator
 * and are deliberately left out of the fillable list.
 */
#[Fillable([
    'item_group_id', 'metal_type_id', 'purity_id', 'making_charge_id',
    'name', 'description', 'gross_weight', 'other_deduction', 'is_active',
])]
class Item extends Model
{
    use SoftDeletes;

    /** One carat is a fifth of a gram. */
    public const CARAT_TO_GRAM = 0.2;

    protected function casts(): array
    {
        return [
            'gross_weight' => 'decimal:3',
            'stone_weight_grams' => 'decimal:3',
            'diamond_weight_grams' => 'decimal:3',
            'other_deduction' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class);
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function makingCharge(): BelongsTo
    {
        return $this->belongsTo(MakingCharge::class);
    }

    public function stones(): HasMany
    {
        return $this->hasMany(ItemStone::class)->where('kind', StoneMaster::KIND_STONE);
    }

    public function diamonds(): HasMany
    {
        return $this->hasMany(ItemStone::class)->where('kind', StoneMaster::KIND_DIAMOND);
    }

    public function itemStones(): HasMany
    {
        return $this->hasMany(ItemStone::class);
    }

    /**
     * Indicative metal value at the given date's rate. Purely for display on the
     * item screens — the quotation recomputes it against its own date.
     */
    public function metalValueOn(?Carbon $date = null): ?float
    {
        $ratePerGram = $this->purity?->ratePerGramOn($date);

        if ($ratePerGram === null) {
            return null;
        }

        return round((float) $this->net_weight * (float) $ratePerGram, 2);
    }

    public function stoneValue(): float
    {
        return (float) $this->itemStones->sum('amount');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

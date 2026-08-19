<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['kind', 'name', 'code', 'shape', 'quality', 'colour', 'size', 'rate_unit', 'default_rate', 'sale_rate', 'is_active'])]
class StoneMaster extends Model
{
    use SoftDeletes;

    public const KIND_STONE = 'stone';

    public const KIND_DIAMOND = 'diamond';

    public const KINDS = [self::KIND_STONE, self::KIND_DIAMOND];

    /** Rate units and how each one multiplies out on an item line. */
    public const RATE_UNITS = [
        'piece' => 'Per Piece',
        'gram' => 'Per Gram',
        'carat' => 'Per Carat',
        'fixed' => 'Fixed Amount',
    ];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'sale_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The rate to charge a customer.
     *
     * A null sale_rate is a real state, not missing data: it means "track the cost
     * rate", so raising the cost lifts the sale price with it until someone sets an
     * explicit figure. Every read goes through here rather than the column.
     */
    public function effectiveSaleRate(): float
    {
        return (float) ($this->sale_rate ?? $this->default_rate);
    }

    public function tracksCostRate(): bool
    {
        return $this->sale_rate === null;
    }

    public function itemStones(): HasMany
    {
        return $this->hasMany(ItemStone::class);
    }

    public function isDiamond(): bool
    {
        return $this->kind === self::KIND_DIAMOND;
    }

    public function rateUnitLabel(): string
    {
        return self::RATE_UNITS[$this->rate_unit] ?? $this->rate_unit;
    }

    public function scopeKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

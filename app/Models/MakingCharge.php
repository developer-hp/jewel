<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'charge_type', 'rate', 'weight_basis', 'is_active'])]
class MakingCharge extends Model
{
    use SoftDeletes;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PER_GRAM = 'per_gram';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPES = [
        self::TYPE_FIXED => 'Fixed Amount',
        self::TYPE_PER_GRAM => 'Per Gram',
        self::TYPE_PERCENTAGE => '% of Metal Value',
    ];

    public const WEIGHT_BASES = [
        'net' => 'Net Weight',
        'gross' => 'Gross Weight',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->charge_type] ?? $this->charge_type;
    }

    /**
     * How the charge reads on screen, e.g. "₹350.00 / g (net)" or "12.00% of metal value".
     */
    public function summary(): string
    {
        return match ($this->charge_type) {
            self::TYPE_FIXED => '₹'.number_format((float) $this->rate, 2),
            self::TYPE_PER_GRAM => '₹'.number_format((float) $this->rate, 2).' / g ('.($this->weight_basis ?? 'net').')',
            self::TYPE_PERCENTAGE => number_format((float) $this->rate, 2).'% of metal value',
            default => (string) $this->rate,
        };
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A pot the shop keeps gold in — KARIGAR, FINE, OLD GOLD and so on.
 *
 * What each holds is the sum of its ledger entries, never a stored figure: a
 * balance column would have to be kept in step with every edit and deletion, and
 * would be wrong the first time one was missed.
 */
#[Fillable(['name', 'reset_on_opening', 'sort_order', 'is_active'])]
class InternalStock extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'reset_on_opening' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'reset_on_opening' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(InternalStockEntry::class);
    }

    /**
     * What this pot holds: everything in, less everything out.
     *
     * Reads `entries_sum_*` when the caller loaded them with
     * {@see scopeWithBalance}, so a page of pots costs one query rather than one
     * each.
     */
    public function balance(): float
    {
        if ($this->entries_sum_in !== null || $this->entries_sum_out !== null) {
            return round((float) $this->entries_sum_in - (float) $this->entries_sum_out, 3);
        }

        return round(
            (float) $this->entries()->whereIn('type', InternalStockEntry::INCOMING)->sum('weight')
                - (float) $this->entries()->where('type', InternalStockEntry::TYPE_OUT)->sum('weight'),
            3
        );
    }

    /**
     * Load both sides of the balance in one go.
     */
    public function scopeWithBalance(Builder $query): void
    {
        $query
            ->withSum(['entries as entries_sum_in' => fn (Builder $q) => $q->whereIn('type', InternalStockEntry::INCOMING)], 'weight')
            ->withSum(['entries as entries_sum_out' => fn (Builder $q) => $q->where('type', InternalStockEntry::TYPE_OUT)], 'weight');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reporting bucket for stock: several item groups roll up into one stock group,
 * so holdings can be summarised by it.
 */
#[Fillable(['name', 'code', 'sort_order', 'is_active'])]
class StockGroup extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function itemGroups(): HasMany
    {
        return $this->hasMany(ItemGroup::class);
    }

    /**
     * Items reached through their item group — what a stock-group report totals.
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(Item::class, ItemGroup::class);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'short_name', 'city', 'address', 'phone', 'is_active'])]
class Supplier extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * How the supplier reads in a dropdown or on the item screen — the short name
     * is what staff actually say, so it leads when present.
     */
    public function label(): string
    {
        return $this->short_name
            ? "{$this->short_name} — {$this->name}"
            : $this->name;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }
}

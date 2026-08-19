<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable(['metal_type_id', 'name', 'touch', 'default_per_grams', 'sort_order', 'is_active'])]
class Purity extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'touch' => 'decimal:3',
            'default_per_grams' => 'decimal:3',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(MetalRate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * The rate that applied on the given date — the latest one dated on or before it.
     * Quotations resolve against their own date so they never silently re-price.
     */
    public function rateOn(?Carbon $date = null): ?MetalRate
    {
        return $this->rates()
            ->whereDate('effective_date', '<=', $date ?? today())
            ->orderByDesc('effective_date')
            ->first();
    }

    public function ratePerGramOn(?Carbon $date = null): ?string
    {
        return $this->rateOn($date)?->rate_per_gram;
    }

    public function label(): string
    {
        return trim(($this->metalType?->name ?? '').' — '.$this->name, ' —');
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

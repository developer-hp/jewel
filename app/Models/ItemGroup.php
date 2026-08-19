<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'prefix', 'code_padding', 'next_sequence', 'metal_type_id', 'sort_order', 'is_active'])]
class ItemGroup extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'code_padding' => 'integer',
            'next_sequence' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Reserve and return the next item code for this group.
     *
     * Takes a row lock so two clerks saving at the same moment cannot be handed
     * the same number. Must be called inside a transaction for the lock to hold.
     */
    public function nextItemCode(): string
    {
        $group = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

        $sequence = $group->next_sequence;
        $group->increment('next_sequence');

        return $group->prefix.str_pad((string) $sequence, $group->code_padding, '0', STR_PAD_LEFT);
    }

    /**
     * What the next code will look like, without consuming it. For form previews only.
     */
    public function previewNextCode(): string
    {
        return $this->prefix.str_pad((string) $this->next_sequence, $this->code_padding, '0', STR_PAD_LEFT);
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

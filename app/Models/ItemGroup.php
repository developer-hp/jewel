<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'prefix', 'code_padding', 'next_sequence', 'metal_type_id', 'stock_group_id', 'sort_order', 'is_active', 'show_in_daily_report'])]
class ItemGroup extends Model
{
    use SoftDeletes;

    /**
     * Mirrors the migration default, so a group created in code reports what it will
     * do on the report without needing a re-read.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'show_in_daily_report' => true,
    ];

    /** Groups the app itself owns and depends on. */
    public const SYSTEM_REPAIR = 'repair';

    public const SYSTEM_ORDER = 'order';

    protected function casts(): array
    {
        return [
            'code_padding' => 'integer',
            'next_sequence' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_in_daily_report' => 'boolean',
        ];
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    /**
     * The reporting bucket this group rolls up into.
     */
    public function stockGroup(): BelongsTo
    {
        return $this->belongsTo(StockGroup::class);
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

    /**
     * A group the app depends on. It cannot be deleted; its prefix stays editable
     * under the same rule as any other group.
     */
    public function isReserved(): bool
    {
        return filled($this->system_key);
    }

    /**
     * The reserved group behind a module, e.g. the Repair group that issues
     * REPAIR0001 for a repaired piece coming back into stock.
     */
    public static function system(string $key): self
    {
        return static::query()->where('system_key', $key)->firstOrFail();
    }

    /**
     * Groups the daily stock report is set to show. Global, not per user.
     */
    public function scopeOnDailyReport(Builder $query): void
    {
        $query->where('show_in_daily_report', true);
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

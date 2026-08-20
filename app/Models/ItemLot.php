<?php

namespace App\Models;

use App\Models\Concerns\HasPhoto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A batch of pieces received from a karigar.
 *
 * The lines declare a quota — so many tags per item group — and items created
 * against the lot count towards it. Status is derived from that count, never set
 * by hand.
 */
#[Fillable([
    'lot_date', 'supplier_id', 'metal_type_id', 'purity_id', 'making_charge_id',
    'total_gross_weight', 'total_net_weight', 'notes',
])]
class ItemLot extends Model
{
    use HasPhoto, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_FINISHED = 'finished';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_FINISHED => 'Finished',
    ];

    /**
     * Mirrors the migration default, so a freshly created instance reports its
     * status without needing a re-read.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'lot_date' => 'date',
            'total_gross_weight' => 'decimal:3',
            'total_net_weight' => 'decimal:3',
        ];
    }

    public function photoDirectory(): string
    {
        return 'item-lots';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ItemLotLine::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    /**
     * Give the lot its code once the database has handed out an id.
     *
     * Derived from the auto-increment rather than a counter of its own, so there is
     * nothing to lock and no way to collide; the unique index is the backstop.
     */
    public function assignCode(): void
    {
        $this->forceFill(['code' => 'LOT'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT)])->save();
    }

    // --- quotas ------------------------------------------------------------

    public function tagsExpected(): int
    {
        return (int) $this->lines()->sum('tags');
    }

    public function piecesExpected(): int
    {
        return (int) $this->lines()->sum('pieces');
    }

    public function tagsUsed(): int
    {
        return $this->items()->count();
    }

    public function remainingTags(): int
    {
        return max(0, $this->tagsExpected() - $this->tagsUsed());
    }

    /**
     * Tags still available on one group's line.
     */
    public function remainingFor(int $itemGroupId): int
    {
        $quota = (int) $this->lines()->where('item_group_id', $itemGroupId)->value('tags');
        $used = $this->items()->where('item_group_id', $itemGroupId)->count();

        return max(0, $quota - $used);
    }

    /**
     * Remaining tags for every line, keyed by group id — one query pair rather than
     * one per line, for the entry screen.
     *
     * @return array<int, int>
     */
    public function remainingByGroup(): array
    {
        $used = $this->items()
            ->selectRaw('item_group_id, count(*) as aggregate')
            ->groupBy('item_group_id')
            ->pluck('aggregate', 'item_group_id');

        return $this->lines->mapWithKeys(fn (ItemLotLine $line) => [
            $line->item_group_id => max(0, $line->tags - (int) ($used[$line->item_group_id] ?? 0)),
        ])->all();
    }

    // --- weights -----------------------------------------------------------

    public function grossEntered(): float
    {
        return round((float) $this->items()->sum('gross_weight'), 3);
    }

    public function netEntered(): float
    {
        return round((float) $this->items()->sum('net_weight'), 3);
    }

    /**
     * True when a declared total is set and the items entered have overshot it.
     * Advisory only — nothing is ever blocked on this.
     */
    public function exceedsGrossTarget(): bool
    {
        return $this->total_gross_weight !== null
            && $this->grossEntered() > (float) $this->total_gross_weight;
    }

    // --- status ------------------------------------------------------------

    /**
     * Recompute and persist the status. Driven by ItemObserver so every path that
     * creates or removes an item keeps the lot honest.
     */
    public function refreshStatus(): void
    {
        $used = $this->tagsUsed();
        $expected = $this->tagsExpected();

        $status = match (true) {
            $used === 0 => self::STATUS_PENDING,
            $expected > 0 && $used >= $expected => self::STATUS_FINISHED,
            default => self::STATUS_IN_PROGRESS,
        };

        if ($status !== $this->status) {
            $this->forceFill(['status' => $status])->save();
        }
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusVariant(): string
    {
        return match ($this->status) {
            self::STATUS_FINISHED => 'success',
            self::STATUS_IN_PROGRESS => 'warning',
            default => 'secondary',
        };
    }

    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }
}

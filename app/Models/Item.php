<?php

namespace App\Models;

use App\Models\Concerns\HasPhoto;
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
    'item_group_id', 'item_lot_id', 'repair_form_line_id', 'order_form_line_id', 'supplier_id',
    'metal_type_id', 'purity_id', 'making_charge_id',
    'huid',
    'name', 'description', 'gross_weight', 'other_deduction', 'is_active',
    'extra_charge_1', 'extra_charge_1_label',
    'extra_charge_2', 'extra_charge_2_label',
])]
class Item extends Model
{
    use HasPhoto, SoftDeletes;

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
            'extra_charge_1' => 'decimal:2',
            'extra_charge_2' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function photoDirectory(): string
    {
        return 'items';
    }

    public function itemLot(): BelongsTo
    {
        return $this->belongsTo(ItemLot::class);
    }

    public function itemGroup(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class);
    }

    /**
     * The repair line this piece came back against, when it did.
     */
    public function repairFormLine(): BelongsTo
    {
        return $this->belongsTo(RepairFormLine::class);
    }

    /**
     * The order line holding this piece — set either by reserving it from stock or
     * by making it to order. While set, the piece is spoken for.
     */
    public function orderFormLine(): BelongsTo
    {
        return $this->belongsTo(OrderFormLine::class);
    }

    /**
     * Promised to a customer, so not available to anyone else.
     */
    public function isReserved(): bool
    {
        return $this->order_form_line_id !== null;
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

    /**
     * Stone rows collapsed into one bucket for the printed tag.
     *
     * A 110 x 18 mm tag cannot carry a line per stone category, so the rows are
     * totalled. Carat and pieces are kept apart because a piece-rate stone and a
     * carat-rate stone cannot be added together.
     *
     * @return array{pieces: int, carat: float, grams: float, amount: float}
     */
    public function stoneSummary(): array
    {
        return $this->summariseStones(StoneMaster::KIND_STONE);
    }

    /**
     * @return array{pieces: int, carat: float, grams: float, amount: float}
     */
    public function diamondSummary(): array
    {
        return $this->summariseStones(StoneMaster::KIND_DIAMOND);
    }

    /**
     * @return array{pieces: int, carat: float, grams: float, amount: float}
     */
    private function summariseStones(string $kind): array
    {
        $rows = $this->itemStones->where('kind', $kind);

        return [
            'pieces' => (int) $rows->sum('pieces'),
            'carat' => round((float) $rows->sum('weight_carat'), 3),
            'grams' => round((float) $rows->sum('weight_grams'), 3),
            'amount' => round((float) $rows->sum('amount'), 2),
        ];
    }

    /**
     * The extra charges that carry a value, with their captions. Slots left at
     * zero are omitted so the tag stays sparse.
     *
     * @return array<int, array{label: string, amount: float}>
     */
    public function extraChargeLines(): array
    {
        $lines = [];

        foreach ([1, 2] as $slot) {
            $amount = (float) $this->{"extra_charge_{$slot}"};

            if ($amount <= 0) {
                continue;
            }

            $lines[] = [
                'label' => trim((string) $this->{"extra_charge_{$slot}_label"}) ?: "E{$slot}",
                'amount' => round($amount, 2),
            ];
        }

        return $lines;
    }

    public function extraChargeTotal(): float
    {
        return round((float) $this->extra_charge_1 + (float) $this->extra_charge_2, 2);
    }

    /**
     * Every stone and diamond charge combined — the tag's single STAMT line.
     */
    public function totalStoneCharge(): float
    {
        return round($this->stoneValue(), 2);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

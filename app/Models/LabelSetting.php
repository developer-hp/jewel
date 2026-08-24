<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One named template for the printed item tag.
 *
 * A metal type may point at a template; anything that does not falls back to the
 * one flagged default. That is the whole of the resolution rule — see forItem().
 */
#[Fillable([
    'name', 'layout', 'shop_name',
    'tag_width_mm', 'tag_height_mm', 'margin_mm', 'font_size_pt', 'max_stone_rows',
    'show_gross', 'show_net', 'show_purity', 'show_huid', 'show_stone', 'show_diamond',
    'show_stone_rate', 'show_extra_charges', 'show_oc', 'show_making_charge',
    'show_item_name', 'show_shop_name',
    'qr_enabled', 'qr_content', 'qr_size_mm',
])]
class LabelSetting extends Model
{
    /** One millimetre in PostScript points, for dompdf's paper box. */
    public const MM_TO_POINTS = 2.83465;

    /** The identity block plus columns of KEY: value pairs — the original tag. */
    public const LAYOUT_STANDARD = 'standard';

    /** A row per stone: code, weight, pieces, rate, amount, reconciling to OC. */
    public const LAYOUT_STONE_DETAIL = 'stone_detail';

    /** A row per diamond: sieve, DW, DR and the shape. */
    public const LAYOUT_DIAMOND_DETAIL = 'diamond_detail';

    public const LAYOUTS = [
        self::LAYOUT_STANDARD => 'Standard',
        self::LAYOUT_STONE_DETAIL => 'Stone Detail',
        self::LAYOUT_DIAMOND_DETAIL => 'Diamond Detail',
    ];

    /**
     * The detail layouts stack rows vertically. Below this the tag runs onto a
     * second page, which wastes a label every time it prints.
     */
    public const DETAIL_MIN_HEIGHT_MM = 25;

    public const QR_CONTENTS = [
        'item_code' => 'Item code',
        'item_url' => 'Link to the item page',
    ];

    /**
     * Mirrors the migration defaults. Without these a newly built template comes
     * back with null dimensions until it is re-read, and the first tag printed on a
     * fresh install renders as a 0 x 0 page. It is also what the create screen
     * pre-fills, so it has to match the columns exactly.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'layout' => self::LAYOUT_STANDARD,
        'is_default' => false,
        'tag_width_mm' => 110,
        'tag_height_mm' => 18,
        'margin_mm' => 2,
        'font_size_pt' => 6,
        'max_stone_rows' => 6,
        'show_gross' => true,
        'show_net' => true,
        'show_purity' => true,
        'show_huid' => false,
        'show_stone' => true,
        'show_diamond' => true,
        'show_stone_rate' => true,
        'show_extra_charges' => true,
        'show_oc' => true,
        'show_making_charge' => true,
        'show_item_name' => true,
        'show_shop_name' => false,
        'qr_enabled' => false,
        'qr_content' => 'item_code',
        'qr_size_mm' => 14,
    ];

    protected function casts(): array
    {
        return [
            'tag_width_mm' => 'decimal:2',
            'tag_height_mm' => 'decimal:2',
            'margin_mm' => 'decimal:2',
            'font_size_pt' => 'decimal:1',
            'qr_size_mm' => 'decimal:2',
            'max_stone_rows' => 'integer',
            'is_default' => 'boolean',
            'show_gross' => 'boolean',
            'show_net' => 'boolean',
            'show_purity' => 'boolean',
            'show_huid' => 'boolean',
            'show_stone' => 'boolean',
            'show_diamond' => 'boolean',
            'show_stone_rate' => 'boolean',
            'show_extra_charges' => 'boolean',
            'show_oc' => 'boolean',
            'show_making_charge' => 'boolean',
            'show_item_name' => 'boolean',
            'show_shop_name' => 'boolean',
            'qr_enabled' => 'boolean',
        ];
    }

    /**
     * Exactly one default, enforced here rather than by the database.
     *
     * A unique index on a generated column would also survive a hand-written UPDATE,
     * but it inverts the order of a promotion — the old default has to be demoted
     * before the new one is saved, or the write aborts — which rules out demoting
     * from a saved() hook and makes replicate() fragile. For a handful of rows edited
     * by one admin at a time, the hook plus default()'s self-heal is the better
     * trade. Please do not "fix" this into an index without reading default().
     */
    protected static function booted(): void
    {
        static::saved(function (self $setting) {
            if (! $setting->is_default) {
                return;
            }

            // A mass update fires no model events, so this cannot recurse.
            static::query()
                ->whereKeyNot($setting->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });

        static::deleting(function (self $setting) {
            if ($setting->is_default) {
                // A backstop. The controller catches this first and flashes a message,
                // because an uncaught exception here would be a 500.
                throw new DomainException('The default label template cannot be deleted.');
            }
        });
    }

    public function metalTypes(): HasMany
    {
        return $this->hasMany(MetalType::class);
    }

    /**
     * The template to fall back to, created on first read so a fresh install can
     * print before anyone opens the settings screen.
     */
    public static function default(): self
    {
        $default = static::query()->where('is_default', true)->first();

        if ($default) {
            return $default;
        }

        // A database edited by hand can end up with no default at all. The oldest
        // row is the deterministic stand-in, and it is promoted so the next read is
        // not a second guess.
        $oldest = static::query()->oldest('id')->first();

        if ($oldest) {
            $oldest->makeDefault();

            return $oldest;
        }

        return tap(static::create([
            'name' => 'Standard Tag',
            'layout' => self::LAYOUT_STANDARD,
        ]), fn (self $setting) => $setting->makeDefault());
    }

    /**
     * The template this item prints with: its metal type's, or the default.
     *
     * Resolves one item at a time. A bulk print should group by metal_type_id and
     * eager-load metalType.labelSetting rather than calling this in a loop.
     */
    public static function forItem(Item $item): self
    {
        $item->loadMissing('metalType.labelSetting');

        return $item->metalType?->labelSetting ?? static::default();
    }

    /**
     * @deprecated Use default() for the fallback template, or forItem() to resolve
     *             the one an item should print with.
     */
    public static function current(): self
    {
        return static::default();
    }

    /**
     * is_default is deliberately not fillable, so promotion only ever happens here —
     * a stray update() or replicate() cannot move the flag by accident.
     */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            $this->forceFill(['is_default' => true])->save();
        });
    }

    public function layoutLabel(): string
    {
        return self::LAYOUTS[$this->layout] ?? $this->layout;
    }

    public function isDetailLayout(): bool
    {
        return in_array($this->layout, [self::LAYOUT_STONE_DETAIL, self::LAYOUT_DIAMOND_DETAIL], true);
    }

    /**
     * The tallest a QR may print without pushing the tag onto a second page.
     */
    public function maxQrSizeMm(): float
    {
        return max(0.0, round((float) $this->tag_height_mm - (2 * (float) $this->margin_mm), 2));
    }

    /**
     * QR edge in mm, clamped to what the tag can actually hold. Settings edited
     * straight in the database bypass validation, so the clamp lives here too.
     */
    public function effectiveQrSizeMm(): float
    {
        return min((float) $this->qr_size_mm, $this->maxQrSizeMm());
    }

    /**
     * Paper box for dompdf: [x0, y0, width, height] in points.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function paperBox(): array
    {
        return [
            0.0,
            0.0,
            round((float) $this->tag_width_mm * self::MM_TO_POINTS, 2),
            round((float) $this->tag_height_mm * self::MM_TO_POINTS, 2),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for the printed item tag.
 */
#[Fillable([
    'shop_name',
    'tag_width_mm', 'tag_height_mm', 'margin_mm', 'font_size_pt',
    'show_gross', 'show_net', 'show_purity', 'show_huid', 'show_stone', 'show_diamond',
    'show_extra_charges', 'show_shop_name',
    'qr_enabled', 'qr_content', 'qr_size_mm',
])]
class LabelSetting extends Model
{
    /** One millimetre in PostScript points, for dompdf's paper box. */
    public const MM_TO_POINTS = 2.83465;

    public const QR_CONTENTS = [
        'item_code' => 'Item code',
        'item_url' => 'Link to the item page',
    ];

    /**
     * Mirrors the migration defaults. Without these, the row created by
     * firstOrCreate() comes back with null dimensions until it is re-read, and the
     * first tag printed on a fresh install renders as a 0 x 0 page.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tag_width_mm' => 110,
        'tag_height_mm' => 18,
        'margin_mm' => 2,
        'font_size_pt' => 6,
        'show_gross' => true,
        'show_net' => true,
        'show_purity' => true,
        'show_stone' => true,
        'show_diamond' => true,
        'show_extra_charges' => true,
        'show_shop_name' => false,
        'qr_enabled' => false,
        'qr_content' => 'item_code',
        'qr_size_mm' => 12,
    ];

    protected function casts(): array
    {
        return [
            'tag_width_mm' => 'decimal:2',
            'tag_height_mm' => 'decimal:2',
            'margin_mm' => 'decimal:2',
            'font_size_pt' => 'decimal:1',
            'qr_size_mm' => 'decimal:2',
            'show_gross' => 'boolean',
            'show_net' => 'boolean',
            'show_purity' => 'boolean',
            'show_huid' => 'boolean',
            'show_stone' => 'boolean',
            'show_diamond' => 'boolean',
            'show_extra_charges' => 'boolean',
            'show_shop_name' => 'boolean',
            'qr_enabled' => 'boolean',
        ];
    }

    /**
     * The one settings row, created with column defaults on first read so a
     * fresh install can print before anyone opens the settings screen.
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
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

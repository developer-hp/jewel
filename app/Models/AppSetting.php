<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Single-row branding: app name, logos and the sidebar user panel colours.
 */
#[Fillable([
    'app_name', 'media_disk',
    'firm_name', 'firm_city', 'firm_phone', 'firm_website', 'firm_office_phone',
    'angadiya_columns', 'angadiya_slip_height_mm', 'hallmark_next_lot_no',
    'hisab_rate_per_10g',
    'repair_next_ref_no', 'repair_ref_prefix', 'repair_contact_no',
    'repair_metal_type_id', 'repair_purity_id',
    'repair_terms',
    'order_next_ref_no', 'order_ref_prefix', 'order_contact_no', 'order_terms',
    'supplier_order_next_form_no', 'supplier_order_header',
    'single_device_login', 'idle_timeout_minutes', 'idle_warning_seconds',
    'logo_path', 'logo_dark_path', 'logo_small_path',
    'sidebar_user_bg_from', 'sidebar_user_bg_to', 'sidebar_user_text_color',
    'table_header_bg_light', 'table_header_bg_dark',
    'dashboard_hidden_sections', 'settings_cache_enabled',
    'og_estimate_next_ref_no', 'og_estimate_ref_prefix',
    'voucher_next_ref_no', 'voucher_ref_prefix',
    'item_estimate_next_ref_no', 'item_estimate_ref_prefix', 'gst_percent',
])]
class AppSetting extends Model
{
    /**
     * The three logo slots and the theme image each falls back to.
     */
    public const LOGO_FALLBACKS = [
        'logo_path' => 'theme/assets/images/logo.png',
        'logo_dark_path' => 'theme/assets/images/logo-dark.png',
        'logo_small_path' => 'theme/assets/images/logo-sm.png',
    ];

    /**
     * Mirrors the migration defaults so an unsaved instance is still renderable.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'app_name' => 'Jewel',
        'media_disk' => 'public',
        'angadiya_columns' => 3,
        'angadiya_slip_height_mm' => 45,
        'hallmark_next_lot_no' => 1,
        'hisab_rate_per_10g' => 0,
        'repair_next_ref_no' => 1,
        'repair_ref_prefix' => 'RG',
        'order_next_ref_no' => 1,
        'supplier_order_next_form_no' => 1,
        'order_ref_prefix' => 'CF',
        'single_device_login' => false,
        'idle_timeout_minutes' => 0,
        'idle_warning_seconds' => 60,
        'sidebar_user_bg_from' => '#0acf97',
        'sidebar_user_bg_to' => '#39afd1',
        'sidebar_user_text_color' => '#ffffff',
        // Raw values, so the json column takes its default as text.
        'dashboard_hidden_sections' => '[]',
        'settings_cache_enabled' => false,
        'og_estimate_next_ref_no' => 1,
        'og_estimate_ref_prefix' => '',
        'voucher_next_ref_no' => 1,
        'voucher_ref_prefix' => '',
        'item_estimate_next_ref_no' => 1,
        'item_estimate_ref_prefix' => '',
        'gst_percent' => 3,
    ];

    /**
     * Sections switched off, by key. Empty means the dashboard shows everything.
     *
     * @return array<int, string>
     */
    public function hiddenDashboardSections(): array
    {
        return array_values((array) ($this->dashboard_hidden_sections ?? []));
    }

    /**
     * The dashboard sections this user should actually be shown, in config order.
     *
     * Hidden ones drop out, and so do any whose permission the viewer lacks. What
     * survives still has to have something in it — that is decided per section, by
     * the data service.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visibleDashboardSections(): array
    {
        $hidden = $this->hiddenDashboardSections();
        $user = auth()->user();

        return collect(config('dashboard', []))
            ->reject(fn (array $section) => in_array($section['key'], $hidden, true))
            ->filter(fn (array $section) => ($section['can'] ?? null) === null || $user?->can($section['can']))
            ->values()
            ->all();
    }

    /**
     * Disks item photos may be written to. Credentials for s3 come from .env; only
     * the choice of destination is stored here, so no secret touches the database.
     */
    public const MEDIA_DISKS = [
        'public' => 'Local (public/storage)',
        's3' => 'Amazon S3',
    ];

    protected function casts(): array
    {
        return [
            'angadiya_columns' => 'integer',
            'hallmark_next_lot_no' => 'integer',
            'angadiya_slip_height_mm' => 'decimal:2',
            'hisab_rate_per_10g' => 'decimal:2',
            'repair_next_ref_no' => 'integer',
            'order_next_ref_no' => 'integer',
            'supplier_order_next_form_no' => 'integer',
            'single_device_login' => 'boolean',
            'idle_timeout_minutes' => 'integer',
            'idle_warning_seconds' => 'integer',
            'dashboard_hidden_sections' => 'array',
            'settings_cache_enabled' => 'boolean',
            'og_estimate_next_ref_no' => 'integer',
            'voucher_next_ref_no' => 'integer',
            'item_estimate_next_ref_no' => 'integer',
            'gst_percent' => 'decimal:2',
        ];
    }

    /** Where the cached copy lives, on whatever store CACHE_STORE names. */
    public const CACHE_KEY = 'app_settings';

    /**
     * The settings row, read from cache when that is switched on.
     *
     * This is loaded on every single request — the layout, the sidebar, the
     * dashboard — so it is the one query worth keeping out of the way.
     *
     * The switch itself lives on the row, which sounds circular but is not: the
     * cached copy carries it, so an enabled cache costs one cache read and nothing
     * else. Disabled, the stale copy is dropped and the database answers.
     */
    public static function current(): self
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof self && $cached->settings_cache_enabled) {
            return $cached;
        }

        $settings = static::firstOrCreate([]);

        if ($settings->settings_cache_enabled) {
            Cache::forever(self::CACHE_KEY, $settings);
        } elseif ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        return $settings;
    }

    /**
     * Drop the cached copy. Bound to save and delete below, so nothing can change
     * these settings and leave a stale page behind.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public function idleTimeoutEnabled(): bool
    {
        return $this->idle_timeout_minutes > 0;
    }

    public function idleTimeoutSeconds(): int
    {
        return $this->idle_timeout_minutes * 60;
    }

    /**
     * The warning can never be longer than the timeout itself, or it would be on
     * screen from the moment the user stops typing.
     */
    public function idleWarningSeconds(): int
    {
        return (int) min($this->idle_warning_seconds, max(1, $this->idleTimeoutSeconds() - 1));
    }

    /**
     * Safe for use while booting: returns an unsaved instance carrying the defaults
     * when the table does not exist yet (a fresh clone before the first migrate).
     */
    public static function resolved(): self
    {
        if (! Schema::hasTable('app_settings')) {
            return new self;
        }

        return static::current();
    }

    /**
     * Public URL for a logo slot, falling back to the bundled theme image.
     */
    public function logoUrl(string $slot): string
    {
        $path = $this->{$slot} ?? null;

        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset(self::LOGO_FALLBACKS[$slot] ?? self::LOGO_FALLBACKS['logo_path']);
    }

    public function hasCustomLogo(string $slot): bool
    {
        return filled($this->{$slot}) && Storage::disk('public')->exists($this->{$slot});
    }

    /**
     * The disk item photos are written to, falling back to local if the stored
     * choice is no longer configured in config/filesystems.php.
     */
    public function mediaDisk(): string
    {
        $disk = $this->media_disk ?: 'public';

        return config("filesystems.disks.{$disk}") ? $disk : 'public';
    }

    /**
     * Custom properties the layout writes onto :root, consumed by app-custom.css.
     *
     * Declared as variables rather than as rules because the dynamic <style> block
     * sits above the app-custom.css link — a plain `.table > thead {...}` here would
     * be overridden by the stylesheet. Custom property lookup does not care about
     * source order, so this cascades correctly whatever the link order.
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        $vars = [];

        foreach (['light', 'dark'] as $mode) {
            $pair = $this->tableHeaderColours($mode);

            if ($pair === null) {
                continue;
            }

            $vars["--app-thead-bg-{$mode}"] = $pair['bg'];

            // Omitted when a named default gives nothing to contrast against; the
            // stylesheet then falls back to the theme's text colour.
            if ($pair['text'] !== null) {
                $vars["--app-thead-color-{$mode}"] = $pair['text'];
            }
        }

        return $vars;
    }

    /**
     * The background and text colour for one mode's table header.
     *
     * A colour chosen on the Appearance screen wins and has its text contrast worked
     * out automatically. Otherwise the shipped default from config/appearance.php is
     * used, where the text colour is stated rather than derived. Null means neither
     * is set, so the theme's own grey stands.
     *
     * @return array{bg: string, text: string|null}|null
     */
    public function tableHeaderColours(string $mode): ?array
    {
        $chosen = $this->{"table_header_bg_{$mode}"};

        if (self::isHexColour($chosen)) {
            return ['bg' => $chosen, 'text' => self::readableTextOn($chosen)];
        }

        return self::defaultTableHeaderColours($mode);
    }

    /**
     * The configured default for one mode, or null when config leaves it to the theme.
     *
     * @return array{bg: string, text: string|null}|null
     */
    public static function defaultTableHeaderColours(string $mode): ?array
    {
        // Config is written by a developer, not posted by a user, so any CSS colour
        // is fair game here — `red`, `rgb(…)`, `var(--ct-primary)` — not just hex.
        $bg = self::cssColour(config("appearance.table_header.{$mode}.bg"));

        if ($bg === null) {
            return null;
        }

        $text = self::cssColour(config("appearance.table_header.{$mode}.text"));

        // Auto-contrast can only be computed from a hex value. With a named colour
        // and no `text` configured, leave it out and let the theme's own text
        // colour stand rather than guessing.
        if ($text === null && self::isHexColour($bg)) {
            $text = self::readableTextOn($bg);
        }

        return ['bg' => $bg, 'text' => $text];
    }

    /**
     * A CSS colour from config, or null if unusable.
     *
     * These land inside a <style> block, so anything that could close the
     * declaration is rejected outright.
     */
    public static function cssColour(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 64) {
            return null;
        }

        return preg_match('/[;{}<>@]/', $value) === 1 ? null : $value;
    }

    public static function isHexColour(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Black or white, whichever reads better on the given background.
     *
     * Uses the WCAG relative-luminance weighting rather than a plain average, so a
     * saturated green is correctly treated as light and a saturated blue as dark.
     */
    public static function readableTextOn(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance > 0.55 ? '#212529' : '#ffffff';
    }

    /**
     * CSS for the sidebar user panel. The theme sets a background photo there, so
     * the gradient has to override background-image, not just background-color.
     */
    public function sidebarUserCss(): string
    {
        return sprintf(
            'background-image: linear-gradient(135deg, %s 0%%, %s 100%%); background-color: %s; color: %s;',
            $this->sidebar_user_bg_from,
            $this->sidebar_user_bg_to,
            $this->sidebar_user_bg_from,
            $this->sidebar_user_text_color,
        );
    }
}

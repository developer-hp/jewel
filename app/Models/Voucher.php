<?php

namespace App\Models;

use App\Models\Concerns\HasOrderReference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use NumberFormatter;

/**
 * A payment out, in cash or by cheque — often an advance against an order.
 */
#[Fillable([
    'voucher_date', 'sales_person_id', 'sales_person_name', 'payment_mode',
    'direction', 'order_form_id', 'description', 'amount',
])]
class Voucher extends Model
{
    use HasOrderReference, SoftDeletes;

    public const MODE_CASH = 'cash';

    public const MODE_CHEQUE = 'cheque';

    public const MODES = [
        self::MODE_CASH => 'Cash',
        self::MODE_CHEQUE => 'Cheque',
    ];

    protected $attributes = [
        'payment_mode' => self::MODE_CASH,
        'amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'ref_no' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(SalesPerson::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reserve the next reference number, under the settings row lock. See
     * OgEstimate::nextRefNo() — the two counters are independent.
     */
    public static function nextRefNo(): int
    {
        AppSetting::current();

        $settings = AppSetting::query()->lockForUpdate()->firstOrFail();
        $refNo = max(1, (int) $settings->voucher_next_ref_no);

        $settings->forceFill(['voucher_next_ref_no' => $refNo + 1])->save();

        return $refNo;
    }

    public static function refPrefix(): string
    {
        return (string) (AppSetting::current()->voucher_ref_prefix ?? '');
    }

    public function reference(): string
    {
        return trim(static::refPrefix().' '.$this->ref_no);
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->payment_mode] ?? $this->payment_mode;
    }

    /**
     * The amount spelled out for the printed voucher.
     *
     * Uses the intl extension's spellout, in the Indian locale so large figures read
     * as lakh and crore rather than million.
     */
    public function amountInWords(): string
    {
        $amount = (float) $this->amount;
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $speller = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);

        $words = ucwords($speller->format($rupees)).' Rupees';

        if ($paise > 0) {
            $words .= ' and '.ucwords($speller->format($paise)).' Paise';
        }

        return $words.' Only';
    }

    /**
     * The rate block on the printed voucher.
     *
     * Taken from the order this voucher is against: purities that order has pinned
     * print with their rate and it reads "fixed"; with nothing pinned it reads "open"
     * and the lines are left blank to write on.
     *
     * @return array{fixed: bool, rows: array<int, array{label: string, rate: float|null}>}|null
     */
    public function rateBlock(): ?array
    {
        if (! $this->isAgainstOrder()) {
            return null;
        }

        $lines = $this->orderForm?->lines ?? collect();

        // Whatever the order pinned, keyed by purity.
        $pinned = $lines->filter->isRateFixed()
            ->groupBy('purity_id')
            ->map(fn ($group) => (float) $group->first()->fixed_rate_per_gram);

        $purities = Purity::active()
            ->with('metalType')
            ->whereRelation('metalType', 'code', 'GOLD')
            ->ordered()
            ->get();

        return [
            'fixed' => $pinned->isNotEmpty(),
            'rows' => $purities->map(fn (Purity $purity) => [
                'label' => $purity->name,
                'rate' => $pinned->get($purity->id),
            ])->all(),
        ];
    }

    public function scopeAgainstOrders(Builder $query): void
    {
        $query->whereNotNull('order_form_id');
    }
}

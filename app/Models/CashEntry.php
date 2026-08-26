<?php

namespace App\Models;

use App\Services\CashMath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money taken or paid at the counter, settling one document.
 *
 * **Read the fillable list, and then read what is missing from it.** Every figure
 * that decides how much money this represents — final_amount, gold_weight,
 * gold_amount — and every foreign key naming the document it settles is set by the
 * controller from a server-side reload, never from the request. Were final_amount
 * fillable, a crafted post could book five lakh against a five hundred rupee estimate
 * and the over-payment guard would wave it through, because the guard compares
 * against that very field.
 */
#[Fillable([
    'entry_date', 'cash_drawer_id', 'cash_event',
    'cash_amount', 'cheque_amount',
    'cheque_number', 'cheque_name', 'cheque_mobile', 'cheque_bank',
])]
class CashEntry extends Model
{
    use SoftDeletes;

    /** Money into the till. */
    public const EVENT_IN = 'in';

    /** Money out of it. */
    public const EVENT_OUT = 'out';

    public const EVENTS = [self::EVENT_IN => 'IN', self::EVENT_OUT => 'OUT'];

    protected $attributes = [
        'cash_event' => self::EVENT_IN,
        'final_amount' => 0,
        'cash_amount' => 0,
        'cheque_amount' => 0,
        'gold_weight' => 0,
        'gold_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'ref_no' => 'integer',
            'final_amount' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'cheque_amount' => 'decimal:2',
            'gold_weight' => 'decimal:3',
            'gold_amount' => 'decimal:2',
        ];
    }

    // --- relations ------------------------------------------------------------

    /**
     * Deliberately a live relation and not a snapshot, unlike everything else here:
     * a drawer is an ongoing account rather than a printed document, so renaming
     * "Counter 1" should rename it on every entry.
     */
    public function drawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class, 'cash_drawer_id');
    }

    public function itemEstimate(): BelongsTo
    {
        return $this->belongsTo(ItemEstimate::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function ogEstimate(): BelongsTo
    {
        return $this->belongsTo(OgEstimate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- the counter ----------------------------------------------------------

    /**
     * Reserve the next reference number.
     *
     * Takes the settings row under a lock so two clerks saving at the same moment
     * cannot be handed the same number. Must run inside a transaction for the lock
     * to hold; the unique index on ref_no is the backstop.
     */
    public static function nextRefNo(): int
    {
        AppSetting::current();

        $settings = AppSetting::query()->lockForUpdate()->firstOrFail();

        $refNo = max(1, (int) $settings->cash_entry_next_ref_no);

        $settings->forceFill(['cash_entry_next_ref_no' => $refNo + 1])->save();

        return $refNo;
    }

    public static function refPrefix(): string
    {
        return (string) (AppSetting::current()->cash_entry_ref_prefix ?? '');
    }

    public function reference(): string
    {
        return trim(static::refPrefix().' '.$this->ref_no);
    }

    // --- the document it settles ------------------------------------------------

    /**
     * The estimate or the voucher, whichever this entry is against.
     */
    public function document(): ItemEstimate|Voucher|null
    {
        return $this->itemEstimate ?? $this->voucher;
    }

    /**
     * What the form's single document control holds — the same one-control,
     * two-columns grammar HasOrderReference uses for "in" / "out" / "order:12".
     */
    public function documentValue(): ?string
    {
        if ($this->item_estimate_id) {
            return 'estimate:'.$this->item_estimate_id;
        }

        return $this->voucher_id ? 'voucher:'.$this->voucher_id : null;
    }

    /**
     * Split that control's value back into the two columns. Only ever sets one, so
     * the "exactly one" invariant cannot be broken through this path.
     *
     * @return array{item_estimate_id: int|null, voucher_id: int|null}
     */
    public static function splitDocumentReference(?string $value): array
    {
        if ($value !== null && str_starts_with($value, 'estimate:')) {
            return ['item_estimate_id' => (int) substr($value, 9), 'voucher_id' => null];
        }

        if ($value !== null && str_starts_with($value, 'voucher:')) {
            return ['item_estimate_id' => null, 'voucher_id' => (int) substr($value, 8)];
        }

        return ['item_estimate_id' => null, 'voucher_id' => null];
    }

    // --- everything derived, through the one service -----------------------------

    public function settledAmount(): float
    {
        return app(CashMath::class)->settled($this);
    }

    public function discount(): float
    {
        return app(CashMath::class)->discount($this);
    }

    public function signedAmount(): float
    {
        return app(CashMath::class)->signed($this);
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->cash_event] ?? $this->cash_event;
    }
}

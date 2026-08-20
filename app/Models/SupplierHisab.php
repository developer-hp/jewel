<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One day's running account with a supplier.
 *
 * The supplier is owed some fine gold (fine baki) and some cash (cash baki). That is
 * settled either in gold — scrap handed over, whose fine weight comes from its gross
 * weight and touch — or in cash at the day's rate. Whatever fine is left after the gold
 * rows turns into cash, and the resulting payout is cash apvi.
 *
 * Every figure on the slip is derived here; nothing summed is stored, so editing a
 * payment row can never leave a stale total behind.
 */
#[Fillable(['hisab_date', 'supplier_id', 'supplier_label', 'fine_baki', 'cash_baki'])]
class SupplierHisab extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'fine_baki' => 0,
        'cash_baki' => 0,
    ];

    protected function casts(): array
    {
        return [
            'hisab_date' => 'date',
            'fine_baki' => 'decimal:3',
            'cash_baki' => 'decimal:2',
            'rate_per_gram' => 'decimal:4',
            'settled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierHisabPayment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOnDate(Builder $query, Carbon $date): void
    {
        $query->whereDate('hisab_date', $date);
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    // --- derived figures ----------------------------------------------------

    /**
     * Fine gold actually handed over — the FINE KAPI card, and the FINE total on
     * the slip's TOTAL PAID row.
     */
    public function fineKapi(): float
    {
        return round($this->payments->sum(fn (SupplierHisabPayment $row) => $row->fineWeight()), 3);
    }

    /**
     * Gross weight of the scrap handed over — the Gross Weight box on the slip.
     */
    public function grossPaid(): float
    {
        return round((float) $this->payments->sum('gross_weight'), 3);
    }

    /**
     * Fine still owed once the gold rows are counted. Negative when more gold was
     * handed over than was owed, which is legitimate — it means cash comes back.
     */
    public function fineRemaining(): float
    {
        return round((float) $this->fine_baki - $this->fineKapi(), 3);
    }

    /**
     * The per-gram rate this hisab settles at.
     *
     * The snapshot taken on settling wins, so changing today's rate afterwards cannot
     * rewrite a slip that has already printed. An unsettled hisab quotes the live rate.
     */
    public function ratePerGram(): float
    {
        if ($this->rate_per_gram !== null) {
            return (float) $this->rate_per_gram;
        }

        return static::currentRatePerGram();
    }

    /**
     * The rate box on the slip, which quotes per 10 grams as the trade does.
     */
    public function ratePer10g(): float
    {
        return round($this->ratePerGram() * 10, 2);
    }

    /**
     * Cash value of the fine still owed, before the outstanding cash is added.
     */
    public function cashForRemainingFine(): float
    {
        return round($this->fineRemaining() * $this->ratePerGram(), 2);
    }

    /**
     * The payout: whatever fine is left, converted at the rate, plus the cash owed.
     */
    public function cashApvi(): float
    {
        return static::roundToTen($this->cashForRemainingFine() + (float) $this->cash_baki);
    }

    /**
     * Today's rate per gram, from the single box on the hisab screen.
     */
    public static function currentRatePerGram(): float
    {
        return round((float) AppSetting::current()->hisab_rate_per_10g / 10, 4);
    }

    /**
     * Cash is settled to the nearest ten rupees at the counter.
     *
     * PHP rounds halves away from zero, so 63855 lands on 63860 — which is what the
     * shop's existing summaries show.
     */
    public static function roundToTen(float $amount): float
    {
        return round($amount / 10) * 10;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasOrderReference;
use App\Services\EstimateLineMath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Old gold brought in over the counter, valued line by line.
 *
 * Nothing summed is stored: the totals come from EstimateLineMath, so the screen, the
 * printed copy and any later report all read the same rule.
 */
#[Fillable([
    'estimate_date', 'customer_id', 'customer_name', 'contact_no', 'address',
    'sales_person_id', 'sales_person_name', 'direction', 'order_form_id',
])]
class OgEstimate extends Model
{
    use HasOrderReference, SoftDeletes;

    protected function casts(): array
    {
        return [
            'estimate_date' => 'date',
            'ref_no' => 'integer',
        ];
    }

    /**
     * The cash entry that settled this, if any.
     *
     * Exists so the cash lookup can say whereDoesntHave('cashEntry'). Because
     * CashEntry soft-deletes, that query carries deleted_at IS NULL for free — which
     * is what makes deleting an entry release its documents, with no extra clause
     * anywhere.
     */
    public function cashEntry(): HasOne
    {
        return $this->hasOne(CashEntry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OgEstimateLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        $refNo = max(1, (int) $settings->og_estimate_next_ref_no);

        $settings->forceFill(['og_estimate_next_ref_no' => $refNo + 1])->save();

        return $refNo;
    }

    public static function refPrefix(): string
    {
        return (string) (AppSetting::current()->og_estimate_ref_prefix ?? '');
    }

    public function reference(): string
    {
        return trim(static::refPrefix().' '.$this->ref_no);
    }

    /**
     * @return object{gross: float, net: float, fine: float, value: float}
     */
    public function totals(): object
    {
        return app(EstimateLineMath::class)->totals($this->lines);
    }
}

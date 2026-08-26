<?php

namespace App\Models;

use App\Services\ItemEstimateMath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A quote for stock going out — the jadtar estimate.
 *
 * Nothing summed is stored: every figure comes from ItemEstimateMath, so the screen,
 * the printed copy and any later report read one rule.
 */
#[Fillable([
    'estimate_date', 'customer_id', 'customer_name', 'contact_no', 'address',
    'sales_person_id', 'sales_person_name', 'order_form_id', 'og_estimate_id',
    'gst_enabled', 'gst_percent', 'show_photo',
])]
class ItemEstimate extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'gst_enabled' => false,
        'gst_percent' => 0,
        'show_photo' => false,
    ];

    protected function casts(): array
    {
        return [
            'estimate_date' => 'date',
            'ref_no' => 'integer',
            'gst_enabled' => 'boolean',
            'gst_percent' => 'decimal:2',
            'show_photo' => 'boolean',
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
        return $this->hasMany(ItemEstimateLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(SalesPerson::class);
    }

    /**
     * The order these lines were built from, when they were.
     */
    public function orderForm(): BelongsTo
    {
        return $this->belongsTo(OrderForm::class);
    }

    /**
     * Old gold coming in against this purchase. Its document prints as a further
     * page; it does not touch the figures here.
     */
    public function ogEstimate(): BelongsTo
    {
        return $this->belongsTo(OgEstimate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reserve the next reference number, under the settings row lock. Its own
     * counter, independent of the OG estimate and the voucher.
     */
    public static function nextRefNo(): int
    {
        AppSetting::current();

        $settings = AppSetting::query()->lockForUpdate()->firstOrFail();
        $refNo = max(1, (int) $settings->item_estimate_next_ref_no);

        $settings->forceFill(['item_estimate_next_ref_no' => $refNo + 1])->save();

        return $refNo;
    }

    public static function refPrefix(): string
    {
        return (string) (AppSetting::current()->item_estimate_ref_prefix ?? '');
    }

    public function reference(): string
    {
        return trim(static::refPrefix().' '.$this->ref_no);
    }

    /**
     * @return object{gross: float, net: float, metal: float, jadtar: float, charges: float, labour: float, oc: float, total: float}
     */
    public function totals(): object
    {
        return app(ItemEstimateMath::class)->totals($this->lines);
    }

    /**
     * The printed box: amount, GST, round-off and grand total.
     *
     * @return object{amount: float, gst: float, round_off: float, total: float}
     */
    public function summary(): object
    {
        return app(ItemEstimateMath::class)
            ->summary($this->lines, (bool) $this->gst_enabled, (float) $this->gst_percent);
    }
}

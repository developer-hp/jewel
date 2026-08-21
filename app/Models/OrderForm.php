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
 * A customer's order.
 *
 * Each line is one piece: either one already in the case, promised to this customer,
 * or one still to be made. A line is ready once a stock item points at it — nothing
 * here stores a status, because readiness is a fact about stock.
 */
#[Fillable([
    'form_date', 'delivery_date', 'customer_name', 'contact_no', 'contact_no_alt',
    'address', 'sales_person_id', 'remarks',
])]
class OrderForm extends Model
{
    use HasPhoto, SoftDeletes;

    protected function casts(): array
    {
        return [
            'form_date' => 'date',
            'delivery_date' => 'date',
            'ref_no' => 'integer',
        ];
    }

    public function photoDirectory(): string
    {
        return 'order-forms';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderFormLine::class)->orderBy('sort_order')->orderBy('id');
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
        // Make sure the singleton exists before locking it — lockForUpdate on a
        // missing row locks nothing.
        AppSetting::current();

        $settings = AppSetting::query()->lockForUpdate()->firstOrFail();

        $refNo = max(1, (int) $settings->order_next_ref_no);

        $settings->forceFill(['order_next_ref_no' => $refNo + 1])->save();

        return $refNo;
    }

    public static function refPrefix(): string
    {
        return (string) (AppSetting::current()->order_ref_prefix ?: 'CF');
    }

    /**
     * What the counter calls this order: "CF 160".
     */
    public function reference(): string
    {
        return trim(static::refPrefix().' '.$this->ref_no);
    }

    // --- ready ---------------------------------------------------------------

    /**
     * Ready once every line has its piece. An order with no lines is not ready —
     * there is nothing to have been made or set aside.
     */
    public function isReady(): bool
    {
        $lines = $this->lines;

        return $lines->isNotEmpty() && $lines->every(fn (OrderFormLine $line) => $line->item !== null);
    }

    public function readyLineCount(): int
    {
        return $this->lines->filter(fn (OrderFormLine $line) => $line->item !== null)->count();
    }

    public function statusLabel(): string
    {
        return $this->isReady() ? 'Ready' : 'Pending';
    }

    /**
     * The sum printed as Total Netweight.
     */
    public function totalNetWeight(): float
    {
        return round((float) $this->lines->sum('net_weight'), 3);
    }

    /**
     * Load the line and item counts the listing needs in one query rather than
     * walking the relations per row.
     */
    public function scopeWithReadyCounts(Builder $query): void
    {
        $query->withCount([
            'lines',
            'lines as ready_lines_count' => fn (Builder $q) => $q->whereHas('item'),
        ]);
    }

    public function scopeReady(Builder $query): void
    {
        $query->has('lines')->whereDoesntHave('lines', fn (Builder $q) => $q->whereDoesntHave('item'));
    }

    public function scopePending(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->doesntHave('lines')
            ->orWhereHas('lines', fn (Builder $sub) => $sub->whereDoesntHave('item')));
    }

    /**
     * Orders the Order Items screen can still make a piece against: a line that is
     * made-to-order and has nothing pointing at it yet.
     */
    public function scopeAwaitingItems(Builder $query): void
    {
        $query->whereHas('lines', fn (Builder $q) => $q->where('made_to_order', true)->whereDoesntHave('item'));
    }
}

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
 * A repair taken in over the counter.
 *
 * The lines are the pieces the customer handed over. Each one goes out to the
 * workshop and comes back as a stock item; a line pointing at an item is a line
 * that is back. When every line is back the form is ready for collection — which
 * is why nothing here stores a status: it is a fact about stock, not a flag.
 */
#[Fillable([
    'form_date', 'delivery_date', 'customer_name', 'contact_no', 'contact_no_alt',
    'address', 'approx_extra_charge', 'remarks',
])]
class RepairForm extends Model
{
    use HasPhoto, SoftDeletes;

    protected function casts(): array
    {
        return [
            'form_date' => 'date',
            'delivery_date' => 'date',
            'ref_no' => 'integer',
            'approx_extra_charge' => 'decimal:2',
        ];
    }

    public function photoDirectory(): string
    {
        return 'repair-forms';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RepairFormLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function salesPersons(): HasMany
    {
        return $this->hasMany(RepairFormSalesPerson::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Who this repair is for. The columns on the form are still the record of what
     * was taken in; this only ties it to the register.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

        $refNo = max(1, (int) $settings->repair_next_ref_no);

        $settings->forceFill(['repair_next_ref_no' => $refNo + 1])->save();

        return $refNo;
    }

    public static function refPrefix(): string
    {
        return (string) (AppSetting::current()->repair_ref_prefix ?: 'RG');
    }

    /**
     * What the counter calls this form: "RG 204".
     */
    public function reference(): string
    {
        return trim(static::refPrefix().' '.$this->ref_no);
    }

    // --- ready ---------------------------------------------------------------

    /**
     * Ready once every piece is back in stock. A form with no lines is not ready —
     * there is nothing to have come back.
     */
    public function isReady(): bool
    {
        $lines = $this->lines;

        return $lines->isNotEmpty() && $lines->every(fn (RepairFormLine $line) => $line->item !== null);
    }

    public function readyLineCount(): int
    {
        return $this->lines->filter(fn (RepairFormLine $line) => $line->item !== null)->count();
    }

    public function statusLabel(): string
    {
        return $this->isReady() ? 'Ready' : 'Pending';
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

    /**
     * Forms with every line back in stock.
     */
    public function scopeReady(Builder $query): void
    {
        $query->has('lines')->whereDoesntHave('lines', fn (Builder $q) => $q->whereDoesntHave('item'));
    }

    /**
     * Forms still waiting on at least one piece — including one with no lines yet.
     */
    public function scopePending(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->doesntHave('lines')
            ->orWhereHas('lines', fn (Builder $sub) => $sub->whereDoesntHave('item')));
    }

    /**
     * Forms the repair-item screen can still book a piece against.
     */
    public function scopeAwaitingItems(Builder $query): void
    {
        $query->whereHas('lines', fn (Builder $q) => $q->whereDoesntHave('item'));
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasPhoto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Work sent out to a karigar against a numbered slip.
 *
 * Pending until the goods come back. Nothing about the state is stored beyond
 * `received_at`; overdue is a fact about the followup date, read at the time it is
 * asked for rather than kept in a column that would need sweeping every night.
 */
#[Fillable([
    'supplier_id', 'supplier_name', 'order_type_id', 'order_type_name',
    'order_form_ref', 'order_date', 'customer_delivery_date', 'followup_date',
    'description', 'size_pcs', 'sample_desc', 'order_weight', 'sample_weight',
    'special_remarks',
])]
class SupplierOrder extends Model
{
    use HasPhoto, SoftDeletes;

    protected function casts(): array
    {
        return [
            'form_no' => 'integer',
            'order_date' => 'date',
            'customer_delivery_date' => 'date',
            'followup_date' => 'date',
            'order_weight' => 'decimal:3',
            'sample_weight' => 'decimal:3',
            'received_at' => 'datetime',
        ];
    }

    public function photoDirectory(): string
    {
        return 'supplier-orders';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Reserve the next form number.
     *
     * Takes the settings row under a lock so two clerks saving at the same moment
     * cannot be handed the same number. Must run inside a transaction for the lock
     * to hold; the unique index on form_no is the backstop.
     */
    public static function nextFormNo(): int
    {
        // Make sure the singleton exists before locking it — lockForUpdate on a
        // missing row locks nothing.
        AppSetting::current();

        $settings = AppSetting::query()->lockForUpdate()->firstOrFail();

        $formNo = max(1, (int) $settings->supplier_order_next_form_no);

        $settings->forceFill(['supplier_order_next_form_no' => $formNo + 1])->save();

        return $formNo;
    }

    /**
     * What the QR on the office copy carries.
     *
     * Opaque on purpose: a printed slip is then not a link anything can follow, and
     * it says nothing about any other order. Collisions are retried rather than
     * hoped away, and the unique index is the backstop.
     */
    public static function newScanToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::withTrashed()->where('scan_token', $token)->exists());

        return $token;
    }

    // --- state ----------------------------------------------------------------

    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    /**
     * Still out, and the day to chase it has passed.
     */
    public function isOverdue(): bool
    {
        return ! $this->isReceived() && $this->followup_date?->isBefore(today());
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isReceived() => 'Received',
            $this->isOverdue() => 'Overdue',
            default => 'Pending',
        };
    }

    /**
     * The class the listing row carries; shared with the other modules' colouring.
     */
    public function rowClass(): string
    {
        return match (true) {
            $this->isReceived() => 'row-ready',
            $this->isOverdue() => 'row-overdue',
            default => 'row-pending',
        };
    }

    public function markReceived(): void
    {
        $this->forceFill(['received_at' => now()])->save();
    }

    public function scopeReceived(Builder $query): void
    {
        $query->whereNotNull('received_at');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('received_at');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('received_at')->whereDate('followup_date', '<', today());
    }
}

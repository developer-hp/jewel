<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One angadiya slip: who a parcel is going to, and what it is insured for.
 *
 * Name, city and mobile are copied from the supplier when one is picked, then kept
 * on the slip. A supplier's details changing later must not rewrite a slip that has
 * already travelled.
 */
#[Fillable(['supplier_id', 'name', 'city', 'mobile', 'insurance_amount', 'remark'])]
class Angadiya extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'insurance_amount' => 'decimal:2',
            'printed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPrinted(): bool
    {
        return $this->printed_at !== null;
    }

    public function markPrinted(): void
    {
        $this->forceFill(['printed_at' => now()])->save();
    }

    /**
     * The FROM block, from the firm's own details. Returns null when the firm has
     * not been named yet, so the slip can say so rather than print a blank box.
     *
     * @return array{name: string, phone: string}|null
     */
    public function fromBlock(): ?array
    {
        $settings = AppSetting::resolved();

        $name = trim(($settings->firm_name ?? '').' '.($settings->firm_city ?? ''));

        if ($name === '') {
            return null;
        }

        return ['name' => $name, 'phone' => (string) ($settings->firm_phone ?? '')];
    }

    public function scopeUnprinted(Builder $query): void
    {
        $query->whereNull('printed_at');
    }
}

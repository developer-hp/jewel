<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Someone the shop deals with over the counter.
 *
 * The phone number is the identity: taking a repair in looks the customer up by it
 * and creates them if they are new, so the register fills itself rather than being
 * kept by hand.
 */
#[Fillable(['name', 'phone', 'address', 'is_active'])]
class Customer extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The lookup key is always derived, never accepted from a request.
        static::saving(function (Customer $customer) {
            $customer->phone_key = static::phoneKey($customer->phone);
        });
    }

    public function repairForms(): HasMany
    {
        return $this->hasMany(RepairForm::class);
    }

    public function orderForms(): HasMany
    {
        return $this->hasMany(OrderForm::class);
    }

    /**
     * Digits only, so "9712 406367", "9712-406367" and "(9712) 406367" are one
     * person. A country code typed on one entry and not the other is still two —
     * normalising that reliably needs a region, which the counter does not supply.
     */
    public static function phoneKey(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    public static function findByPhone(?string $phone): ?self
    {
        $key = static::phoneKey($phone);

        return $key === '' ? null : static::query()->where('phone_key', $key)->first();
    }

    /**
     * The customer behind this number, created from what was just typed if they are
     * new to the shop.
     *
     * An existing record is returned untouched: the register is built up from first
     * contact, and a name or address typed differently on a later form is not
     * evidence the master is wrong.
     */
    public static function rememberByPhone(?string $phone, string $name, ?string $address = null): ?self
    {
        if (static::phoneKey($phone) === '') {
            return null;
        }

        return static::findByPhone($phone) ?? static::create([
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'is_active' => true,
        ]);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }
}

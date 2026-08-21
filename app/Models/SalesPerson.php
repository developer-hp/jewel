<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Counter staff a repair form can be booked against. Names are snapshotted onto
 * the form, so this master only supplies the list.
 */
#[Fillable(['name', 'phone', 'city', 'sort_order', 'is_active'])]
class SalesPerson extends Model
{
    use SoftDeletes;

    /** The pluralizer would give "sales_people". */
    protected $table = 'sales_persons';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function repairFormLinks(): HasMany
    {
        return $this->hasMany(RepairFormSalesPerson::class);
    }

    /**
     * How the person reads in a dropdown — the city disambiguates two same names.
     */
    public function label(): string
    {
        return $this->city ? "{$this->name} ({$this->city})" : $this->name;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}

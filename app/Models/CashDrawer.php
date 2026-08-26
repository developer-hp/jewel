<?php

namespace App\Models;

use App\Services\CashMath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A till. Money is booked into one, and its balance is the opening figure plus
 * everything taken in since, less everything paid out.
 */
#[Fillable(['name', 'opening_balance', 'sort_order', 'is_active'])]
class CashDrawer extends Model
{
    use SoftDeletes;

    protected $attributes = [
        'opening_balance' => 0,
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CashEntry::class);
    }

    /**
     * This drawer's balance — one query.
     *
     * Fine for a form header or a single drawer. **Do not call it in a loop**: the
     * listing computes every balance in one correlated subselect, in
     * CashDrawerController::data().
     */
    public function balance(): float
    {
        return app(CashMath::class)->balance($this);
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

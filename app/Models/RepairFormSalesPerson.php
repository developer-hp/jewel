<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One salesperson booked against a repair form, with their name as it stood at
 * the time — that is what prints on the office copy.
 */
#[Fillable(['sales_person_id', 'name', 'sort_order'])]
class RepairFormSalesPerson extends Model
{
    /** The pluralizer would give "repair_form_sales_people". */
    protected $table = 'repair_form_sales_persons';

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function repairForm(): BelongsTo
    {
        return $this->belongsTo(RepairForm::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(SalesPerson::class);
    }
}

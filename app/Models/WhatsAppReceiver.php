<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Someone who gets the day opening's reports on WhatsApp.
 */
#[Fillable(['name', 'mobile', 'sort_order', 'is_active'])]
class WhatsAppReceiver extends Model
{
    use SoftDeletes;

    /** The pluraliser splits the class name at the capital A, as with the templates. */
    protected $table = 'whatsapp_receivers';

    protected $attributes = ['sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
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

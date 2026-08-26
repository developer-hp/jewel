<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tills money is booked into.
 *
 * `opening_balance` is a one-time starting figure, not a daily one — the balance
 * shown on the listing is this plus every entry since. There is no day-close.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawers', function (Blueprint $table) {
            $table->id();
            // No code column: a drawer is picked by name from a dropdown, never
            // referenced by code the way an item group references a stock group.
            $table->string('name', 100)->unique();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawers');
    }
};

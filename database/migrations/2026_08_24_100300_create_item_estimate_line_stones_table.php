<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors item_stones: what was quoted, frozen at the moment of quoting.
        Schema::create('item_estimate_line_stones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_estimate_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_master_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['stone', 'diamond']);

            $table->unsignedInteger('pieces')->default(0);
            $table->decimal('weight_carat', 10, 3)->default(0);
            // Derived: weight_carat * 0.2
            $table->decimal('weight_grams', 10, 4)->default(0);

            // Snapshotted from the master, so a later master edit cannot re-price a
            // quote already given to a customer.
            $table->enum('rate_unit', ['piece', 'gram', 'carat', 'fixed']);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);

            $table->boolean('deduct_from_gross')->default(true);
            $table->timestamps();

            $table->index(['item_estimate_line_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_estimate_line_stones');
    }
};

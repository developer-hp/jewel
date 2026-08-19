<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_master_id')->constrained()->restrictOnDelete();
            // Denormalised from the master so the two form sections and reports need no join.
            $table->enum('kind', ['stone', 'diamond']);

            $table->unsignedInteger('pieces')->default(0);
            $table->decimal('weight_carat', 10, 3)->default(0);
            // Derived: weight_carat * 0.2
            $table->decimal('weight_grams', 10, 4)->default(0);

            // Snapshotted from the master at save time — editing the master later must not
            // retroactively change what an existing item is worth.
            $table->enum('rate_unit', ['piece', 'gram', 'carat', 'fixed']);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);

            $table->boolean('deduct_from_gross')->default(true);
            $table->timestamps();

            $table->index(['item_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stones');
    }
};

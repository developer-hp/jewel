<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_lot_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_group_id')->constrained()->restrictOnDelete();

            // Physical pieces received; a pair of earrings is 2 pcs but 1 tag.
            $table->unsignedInteger('pieces')->default(0);
            // Item records to create — this is the quota.
            $table->unsignedInteger('tags')->default(0);

            $table->timestamps();

            // One line per group, so an item's line is found by (lot, group) alone.
            $table->unique(['item_lot_id', 'item_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_lot_lines');
    }
};

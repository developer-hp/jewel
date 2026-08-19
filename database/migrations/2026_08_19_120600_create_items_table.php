<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            // Generated from the group prefix + its running sequence; unique as a last resort guard.
            $table->string('code', 30)->unique();
            $table->foreignId('item_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('metal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purity_id')->constrained()->restrictOnDelete();
            // Selection only — the charge itself is applied at quotation time.
            $table->foreignId('making_charge_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->decimal('gross_weight', 10, 3);
            // Everything below is recomputed server-side by ItemCalculator; never mass-assigned.
            $table->decimal('stone_weight_grams', 10, 3)->default(0);
            $table->decimal('diamond_weight_grams', 10, 3)->default(0);
            // Wax, lac or thread on antique/jadtar pieces.
            $table->decimal('other_deduction', 10, 3)->default(0);
            $table->decimal('net_weight', 10, 3);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['item_group_id', 'metal_type_id', 'purity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

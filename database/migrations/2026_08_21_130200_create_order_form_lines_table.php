<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_form_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            // The piece picked from stock — either the one being promised, or the one
            // a made-to-order line is copied from.
            $table->foreignId('source_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->boolean('made_to_order')->default(false);

            $table->string('description', 255);
            $table->string('size_pcs', 50)->nullable();

            // What the rate is fixed against; lines can differ in metal.
            $table->foreignId('metal_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purity_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('net_weight', 10, 3)->default(0);

            // Labour: an amount plus how to read it, so a quotation can compute from it.
            $table->decimal('lc_amount', 12, 2)->default(0);
            $table->enum('lc_type', ['per_gram', 'percentage', 'fixed'])->default('per_gram');
            $table->decimal('oc_amount', 12, 2)->default(0);

            // Pinned when the counter fixes the day's rate against a ready line.
            $table->decimal('fixed_rate_per_gram', 12, 4)->nullable();
            $table->timestamp('rate_fixed_at')->nullable();

            $table->string('photo_path')->nullable();
            $table->string('photo_disk', 30)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_form_lines');
    }
};

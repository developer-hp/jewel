<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_estimate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            // Null for a line typed by description alone, with no piece behind it.
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255);

            $table->decimal('gross_weight', 12, 3)->default(0);
            // Quoted per ten grams, the way the trade quotes it.
            $table->decimal('rate', 14, 2)->default(0);

            // Labour reads through its type, the same three the Making Charge master has.
            $table->decimal('labour_amount', 14, 2)->default(0);
            $table->enum('labour_type', ['percentage', 'per_gram', 'fixed'])->default('per_gram');

            $table->decimal('oc_amount', 14, 2)->default(0);

            // Net weight, jadtar and the line total are derived by ItemEstimateMath.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_estimate_lines');
    }
};

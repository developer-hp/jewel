<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('og_estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('og_estimate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('description', 255);
            $table->decimal('gross_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3)->default(0);
            // The piece's purity as a percentage, typed per line: scrap over the
            // counter rarely matches a master exactly.
            $table->decimal('touch_percent', 6, 3)->default(0);
            // Quoted per ten grams, the way the trade quotes it.
            $table->decimal('rate', 14, 2)->default(0);

            // Fine weight and line value are derived by EstimateLineMath, never stored.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('og_estimate_lines');
    }
};

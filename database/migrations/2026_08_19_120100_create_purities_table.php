<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_type_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            // Fineness as a percentage, e.g. 91.600 for 22K gold.
            $table->decimal('touch', 6, 3)->nullable();
            // Prefills the rate form: gold is quoted per 10 g, silver per 1000 g.
            $table->decimal('default_per_grams', 8, 3)->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['metal_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purities');
    }
};

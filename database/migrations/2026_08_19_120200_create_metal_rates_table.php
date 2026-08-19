<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purity_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            // The amount exactly as the user typed it, against the basis below.
            $table->decimal('rate', 12, 2);
            $table->decimal('per_grams', 8, 3)->default(10);
            // Derived (rate / per_grams) and stored so pricing is a plain column read.
            $table->decimal('rate_per_gram', 12, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['purity_id', 'effective_date']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_rates');
    }
};

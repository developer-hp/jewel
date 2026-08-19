<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('making_charges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->enum('charge_type', ['fixed', 'per_gram', 'percentage']);
            $table->decimal('rate', 12, 4);
            // Only meaningful for per_gram: which weight the rate multiplies.
            $table->enum('weight_basis', ['net', 'gross'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('making_charges');
    }
};

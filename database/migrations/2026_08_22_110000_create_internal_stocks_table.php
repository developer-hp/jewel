<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // Stored only for now: the opening-reset routine that reads it comes later.
            $table->boolean('reset_on_opening')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_stocks');
    }
};

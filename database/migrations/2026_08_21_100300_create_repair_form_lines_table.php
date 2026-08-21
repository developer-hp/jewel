<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_form_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_form_id')->constrained()->cascadeOnDelete();

            // The PARTICULARS and ARTICLE WEIGHT columns on the printed form.
            $table->string('description', 255);
            $table->decimal('net_weight', 10, 3)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_form_lines');
    }
};

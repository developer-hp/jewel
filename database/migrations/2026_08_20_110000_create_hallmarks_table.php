<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hallmarks', function (Blueprint $table) {
            $table->id();
            // Issued from app_settings.hallmark_next_lot_no under a row lock.
            $table->unsignedInteger('lot_no')->unique();
            $table->date('hallmark_date');

            $table->decimal('cost_per_piece', 12, 2);
            $table->decimal('gross_weight', 12, 3);

            $table->string('photo_path')->nullable();
            $table->string('photo_disk', 20)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('hallmark_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hallmarks');
    }
};

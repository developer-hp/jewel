<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_stock_id')->constrained()->restrictOnDelete();

            // `opening` adds to the balance exactly as `in` does; it stays a type of
            // its own so the opening line is identifiable when the reset routine
            // arrives.
            $table->enum('type', ['in', 'out', 'opening']);
            $table->decimal('weight', 12, 3);
            $table->string('note', 255);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['internal_stock_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_stock_entries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_hisab_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_hisab_id')->constrained()->cascadeOnDelete();

            // Free text on the slip — scrap handed over, not an item from the master.
            $table->string('item_name', 100);
            $table->decimal('gross_weight', 12, 3)->default(0);
            // A percentage: fine weight is gross x touch / 100, derived on the model.
            $table->decimal('touch', 6, 3)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_hisab_payments');
    }
};

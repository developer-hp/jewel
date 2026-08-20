<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hallmark_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hallmark_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_group_id')->constrained()->restrictOnDelete();

            // Prefilled from the group name but editable — this is what prints under
            // PARTICULARS on the docket.
            $table->string('description', 150);

            $table->foreignId('purity_id')->constrained()->restrictOnDelete();

            // The SC column. Printed as the supplier's short name (V-1 … V-200).
            // Nullable so a line can be entered before its vendor is known.
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('pcs_per_quantity')->default(1);
            // total pcs is quantity * pcs_per_quantity — derived, never stored.

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['hallmark_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hallmark_lines');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_lots', function (Blueprint $table) {
            $table->id();
            // LOT00001, derived from the id, so it can only be set after the insert
            // — see ItemLot::assignCode(). Nullable for that one moment; the unique
            // index still guards every real code (MySQL allows repeated NULLs).
            $table->string('code', 20)->nullable()->unique();
            $table->date('lot_date');

            // Batch defaults the entry screen prefills from.
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('metal_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('making_charge_id')->nullable()->constrained()->nullOnDelete();

            // Declared targets; item weights are compared against these, never capped.
            $table->decimal('total_gross_weight', 12, 3)->nullable();
            $table->decimal('total_net_weight', 12, 3)->nullable();

            $table->string('photo_path')->nullable();
            $table->string('photo_disk', 20)->nullable();
            $table->text('notes')->nullable();

            // Derived from items-added vs tags-expected, but stored so the listing can
            // filter and sort without computing per row.
            $table->enum('status', ['pending', 'in_progress', 'finished'])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_lots');
    }
};

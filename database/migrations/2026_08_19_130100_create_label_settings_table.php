<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row table read through LabelSetting::current(). Typed columns rather
     * than a key/value store so the flags validate normally and document themselves.
     */
    public function up(): void
    {
        Schema::create('label_settings', function (Blueprint $table) {
            $table->id();

            $table->string('shop_name', 60)->nullable();

            // Physical tag stock, in millimetres.
            $table->decimal('tag_width_mm', 6, 2)->default(110);
            $table->decimal('tag_height_mm', 6, 2)->default(18);
            $table->decimal('margin_mm', 5, 2)->default(2);
            $table->decimal('font_size_pt', 4, 1)->default(6);

            $table->boolean('show_gross')->default(true);
            $table->boolean('show_net')->default(true);
            $table->boolean('show_purity')->default(true);
            $table->boolean('show_stone')->default(true);
            $table->boolean('show_diamond')->default(true);
            $table->boolean('show_extra_charges')->default(true);
            $table->boolean('show_shop_name')->default(false);

            $table->boolean('qr_enabled')->default(false);
            $table->enum('qr_content', ['item_code', 'item_url'])->default('item_code');
            $table->decimal('qr_size_mm', 5, 2)->default(14);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_settings');
    }
};

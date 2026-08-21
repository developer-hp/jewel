<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Set when a repaired piece comes back and enters stock. Its presence is
            // what marks the line ready — unique, so a line is claimed only once.
            $table->foreignId('repair_form_line_id')->nullable()->unique()->after('item_lot_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['repair_form_line_id']);
            $table->dropUnique(['repair_form_line_id']);
            $table->dropColumn('repair_form_line_id');
        });
    }
};

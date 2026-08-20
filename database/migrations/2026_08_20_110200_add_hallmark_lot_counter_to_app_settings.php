<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Existing dockets are already numbered, so this is editable: set it to
            // the next number in use before the first entry.
            $table->unsignedInteger('hallmark_next_lot_no')->default(1)->after('angadiya_slip_height_mm');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('hallmark_next_lot_no');
        });
    }
};

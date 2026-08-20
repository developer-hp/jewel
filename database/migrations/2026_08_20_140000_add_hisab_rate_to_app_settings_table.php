<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Entered per 10 grams, the way the trade quotes it. The per-gram figure
            // used in the settlement is derived, never stored here.
            $table->decimal('hisab_rate_per_10g', 12, 2)->default(0)->after('hallmark_next_lot_no');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('hisab_rate_per_10g');
        });
    }
};

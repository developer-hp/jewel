<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            // Whether this group appears on the daily stock report. Set from the
            // report itself and shared by everyone — it is a property of the group,
            // not a preference of whoever happens to be looking.
            //
            // Defaults true, so a group added later shows up until someone says not to.
            $table->boolean('show_in_daily_report')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            $table->dropColumn('show_in_daily_report');
        });
    }
};

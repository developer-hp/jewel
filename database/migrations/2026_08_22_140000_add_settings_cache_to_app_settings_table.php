<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Hold this row in the cache instead of reading it on every request.
            //
            // Off by default: it is a performance switch, and something that changes
            // how every page loads should be turned on deliberately rather than
            // arriving with a migration.
            $table->boolean('settings_cache_enabled')->default(false)->after('dashboard_hidden_sections');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('settings_cache_enabled');
        });
    }
};

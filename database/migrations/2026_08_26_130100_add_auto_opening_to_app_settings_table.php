<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the day opening runs by itself.
 *
 * Off by default, and deliberately so: the opening deletes the day's estimates,
 * angadiya slips, hisab and cash entries for good. Something that destructive is
 * switched on knowingly, not inherited from a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('auto_opening_enabled')->default(false)->after('settings_cache_enabled');
            $table->timestamp('last_opening_at')->nullable()->after('auto_opening_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_opening_enabled', 'last_opening_at']);
        });
    }
};

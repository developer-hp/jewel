<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the two landing-page looks to serve.
 *
 * Defaults to the one that shipped first so an install that already switched the
 * page on does not change appearance underneath its customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('landing_layout', 20)->default('fancy')->after('landing_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('landing_layout');
        });
    }
};

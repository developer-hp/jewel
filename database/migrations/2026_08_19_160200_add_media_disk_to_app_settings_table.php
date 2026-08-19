<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Which disk item photos are written to. Credentials stay in .env —
            // only the choice of destination lives here.
            $table->string('media_disk', 20)->default('public')->after('app_name');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('media_disk');
        });
    }
};

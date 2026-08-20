<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Null means "leave it to the theme", which is the current grey. Two
            // colours because one shared value cannot suit both modes — a pale
            // header in dark mode is exactly what went wrong with .table-light.
            $table->string('table_header_bg_light', 20)->nullable()->after('sidebar_user_text_color');
            $table->string('table_header_bg_dark', 20)->nullable()->after('table_header_bg_light');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['table_header_bg_light', 'table_header_bg_dark']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // The reordered menu structure: {scope: [key, key, …]}.
            // A scope is 'sections', 'section:Main', 'group:stock', etc.
            // Items not listed keep their original relative order.
            // Mirrors the shape and intent of dashboard_hidden_sections.
            $table->json('menu_order')->nullable()->after('dashboard_hidden_sections');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('menu_order');
        });
    }
};

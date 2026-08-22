<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // The dashboard sections switched off, by key. Hidden rather than shown
            // is stored on purpose: a section added later then appears by default
            // instead of staying invisible until somebody notices.
            $table->json('dashboard_hidden_sections')->nullable()->after('table_header_bg_dark');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('dashboard_hidden_sections');
        });
    }
};

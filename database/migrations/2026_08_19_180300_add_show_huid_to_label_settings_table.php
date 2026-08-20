<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_settings', function (Blueprint $table) {
            // Off by default so no existing tag layout changes.
            $table->boolean('show_huid')->default(false)->after('show_purity');
        });
    }

    public function down(): void
    {
        Schema::table('label_settings', function (Blueprint $table) {
            $table->dropColumn('show_huid');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // The number printed on a repair form. Kept apart from the firm's general
            // phone so repairs can be answered on their own line; falls back to it.
            $table->string('repair_contact_no', 30)->nullable()->after('repair_ref_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('repair_contact_no');
        });
    }
};

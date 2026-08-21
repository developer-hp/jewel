<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            // Marks a group the app itself owns and depends on — `repair`, `order`.
            // Such a group cannot be deleted; its prefix stays editable.
            $table->string('system_key', 20)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });
    }
};

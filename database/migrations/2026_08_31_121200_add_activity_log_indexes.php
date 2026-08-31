<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The indexes the activity screen actually filters on.
 *
 * The package ships an index on log_name alone and the two polymorphic pairs. Every
 * view of this screen is newest-first and usually narrowed by type or by user, so
 * both of those need created_at beside them or the sort falls back to a filesort over
 * what will become the largest table in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['log_name', 'created_at'], 'activity_log_name_created_index');
            $table->index(['causer_id', 'created_at'], 'activity_log_causer_created_index');
            // Pruning is a delete over a date range and nothing else.
            $table->index('created_at', 'activity_log_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_name_created_index');
            $table->dropIndex('activity_log_causer_created_index');
            $table->dropIndex('activity_log_created_index');
        });
    }
};

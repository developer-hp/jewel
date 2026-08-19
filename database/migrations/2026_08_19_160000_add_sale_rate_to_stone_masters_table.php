<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stone_masters', function (Blueprint $table) {
            // Null is a real state meaning "track default_rate", which is exactly how
            // existing rows should behave — so no backfill.
            // See StoneMaster::effectiveSaleRate().
            $table->decimal('sale_rate', 12, 2)->nullable()->after('default_rate');
        });
    }

    public function down(): void
    {
        Schema::table('stone_masters', function (Blueprint $table) {
            $table->dropColumn('sale_rate');
        });
    }
};

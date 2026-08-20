<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            // Optional: item groups without one simply fall outside a stock-group
            // breakdown, rather than blocking the master.
            $table->foreignId('stock_group_id')->nullable()->after('metal_type_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('item_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_group_id');
        });
    }
};

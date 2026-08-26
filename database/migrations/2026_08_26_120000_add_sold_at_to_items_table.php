<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a piece left the shop.
 *
 * Null means it is still stock, and that is what every stock figure now counts —
 * see App\Services\StockFigures and Item::scopeInStock(). Marking a piece available
 * again clears this, so the column is the whole of the state.
 *
 * Not a boolean, because "when" is the question anyone asks afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->date('sold_at')->nullable()->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('sold_at');
        });
    }
};

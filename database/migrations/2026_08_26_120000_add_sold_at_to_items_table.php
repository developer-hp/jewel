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
 * A timestamp rather than a date, and not only because "when" is the question
 * anyone asks afterwards: the day opening reports on everything that happened since
 * the last one, which is a moment and not a day. The daily stock sheet takes DATE()
 * of it where it wants a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('sold_at');
        });
    }
};

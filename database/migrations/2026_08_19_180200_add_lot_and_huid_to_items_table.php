<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('item_lot_id')->nullable()->after('item_group_id')
                ->constrained()->nullOnDelete();

            // BIS hallmark code. Free text by decision: no uniqueness and no
            // 6-character rule, so 20 leaves room for other formats.
            $table->string('huid', 20)->nullable()->after('code');
            $table->index('huid');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_lot_id');
            $table->dropIndex(['huid']);
            $table->dropColumn('huid');
        });
    }
};

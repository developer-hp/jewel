<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // The twin of repair_form_line_id. One column carries both cases:
            // reserving a stock piece writes it onto that existing item, and making
            // a piece to order writes it onto the new one.
            //
            // Unique, so the reservation is enforced by the database rather than by
            // the application remembering to check — a piece cannot be promised twice.
            $table->foreignId('order_form_line_id')->nullable()->unique()->after('repair_form_line_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['order_form_line_id']);
            $table->dropUnique(['order_form_line_id']);
            $table->dropColumn('order_form_line_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_form_lines', function (Blueprint $table) {
            // Other charges are no longer typed: they are the stones and diamonds
            // plus the two extra charges, so the figure is derived and the column
            // that stored it would only ever go stale.
            $table->dropColumn('oc_amount');

            // Snapshotted off the chosen piece, so a later edit to that item does
            // not silently re-price an order already taken.
            $table->decimal('extra_charge_1', 12, 2)->default(0)->after('lc_type');
            $table->decimal('extra_charge_2', 12, 2)->default(0)->after('extra_charge_1');
        });
    }

    public function down(): void
    {
        Schema::table('order_form_lines', function (Blueprint $table) {
            $table->decimal('oc_amount', 12, 2)->default(0)->after('lc_type');
            $table->dropColumn(['extra_charge_1', 'extra_charge_2']);
        });
    }
};

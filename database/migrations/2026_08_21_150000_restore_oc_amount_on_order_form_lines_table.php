<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_form_lines', function (Blueprint $table) {
            // Other charges are typed again. The form still totals the stones and the
            // chosen piece's extra charges into the box as a starting figure, but the
            // counter has the last word — so it is stored, not derived, and the two
            // extra columns that only existed to feed the derivation are gone.
            $table->decimal('oc_amount', 12, 2)->default(0)->after('lc_type');
            $table->dropColumn(['extra_charge_1', 'extra_charge_2']);
        });
    }

    public function down(): void
    {
        Schema::table('order_form_lines', function (Blueprint $table) {
            $table->decimal('extra_charge_1', 12, 2)->default(0)->after('lc_type');
            $table->decimal('extra_charge_2', 12, 2)->default(0)->after('extra_charge_1');
            $table->dropColumn('oc_amount');
        });
    }
};

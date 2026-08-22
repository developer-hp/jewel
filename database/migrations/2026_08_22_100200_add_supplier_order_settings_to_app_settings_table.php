<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('supplier_order_next_form_no')->default(1)->after('order_terms');
            // The line across the top right of the karigar receipt.
            $table->string('supplier_order_header', 150)->nullable()->after('supplier_order_next_form_no');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['supplier_order_next_form_no', 'supplier_order_header']);
        });
    }
};

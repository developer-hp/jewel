<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // A counter each: the two documents number independently.
            $table->unsignedInteger('og_estimate_next_ref_no')->default(1)->after('order_terms');
            // Empty by default, so the documents print a bare "41" as they do today.
            $table->string('og_estimate_ref_prefix', 10)->default('')->after('og_estimate_next_ref_no');

            $table->unsignedInteger('voucher_next_ref_no')->default(1)->after('og_estimate_ref_prefix');
            $table->string('voucher_ref_prefix', 10)->default('')->after('voucher_next_ref_no');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'og_estimate_next_ref_no', 'og_estimate_ref_prefix',
                'voucher_next_ref_no', 'voucher_ref_prefix',
            ]);
        });
    }
};

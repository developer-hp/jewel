<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('item_estimate_next_ref_no')->default(1)->after('voucher_ref_prefix');
            // Empty by default, so the document prints a bare "43" as it does today.
            $table->string('item_estimate_ref_prefix', 10)->default('')->after('item_estimate_next_ref_no');

            // Snapshotted onto each estimate that is taxed, so changing it here never
            // rewrites a document already printed.
            $table->decimal('gst_percent', 5, 2)->default(3)->after('item_estimate_ref_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'item_estimate_next_ref_no', 'item_estimate_ref_prefix', 'gst_percent',
            ]);
        });
    }
};

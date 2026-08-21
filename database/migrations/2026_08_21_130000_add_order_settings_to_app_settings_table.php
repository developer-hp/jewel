<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('order_next_ref_no')->default(1)->after('repair_terms');
            $table->string('order_ref_prefix', 10)->default('CF')->after('order_next_ref_no');
            // The "For Query" number at the head of the printed order form.
            $table->string('order_contact_no', 30)->nullable()->after('order_ref_prefix');
            $table->text('order_terms')->nullable()->after('order_contact_no');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'order_next_ref_no', 'order_ref_prefix', 'order_contact_no', 'order_terms',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // One-off costs that are neither metal, stone nor making charge —
            // polish, certification, rhodium. Stored only; the quotation applies them.
            $table->decimal('extra_charge_1', 12, 2)->default(0)->after('making_charge_id');
            $table->string('extra_charge_1_label', 20)->nullable()->after('extra_charge_1');
            $table->decimal('extra_charge_2', 12, 2)->default(0)->after('extra_charge_1_label');
            $table->string('extra_charge_2_label', 20)->nullable()->after('extra_charge_2');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'extra_charge_1', 'extra_charge_1_label',
                'extra_charge_2', 'extra_charge_2_label',
            ]);
        });
    }
};

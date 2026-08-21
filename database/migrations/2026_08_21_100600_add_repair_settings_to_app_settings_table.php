<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // The repair form's own reference counter, printed as "{prefix} {ref_no}".
            $table->unsignedInteger('repair_next_ref_no')->default(1)->after('hisab_rate_per_10g');
            $table->string('repair_ref_prefix', 10)->default('RG')->after('repair_next_ref_no');

            // What a returned repair piece is booked into stock as, unless changed
            // on the entry screen.
            $table->foreignId('repair_metal_type_id')->nullable()->after('repair_ref_prefix')
                ->constrained('metal_types')->nullOnDelete();
            $table->foreignId('repair_purity_id')->nullable()->after('repair_metal_type_id')
                ->constrained('purities')->nullOnDelete();

            // The terms block at the foot of the printed repair form.
            $table->text('repair_terms')->nullable()->after('repair_purity_id');

            // The customer copy and the office copy carry different numbers.
            $table->string('firm_website', 150)->nullable()->after('firm_phone');
            $table->string('firm_office_phone', 30)->nullable()->after('firm_website');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropForeign(['repair_metal_type_id']);
            $table->dropForeign(['repair_purity_id']);
            $table->dropColumn([
                'repair_next_ref_no', 'repair_ref_prefix',
                'repair_metal_type_id', 'repair_purity_id', 'repair_terms',
                'firm_website', 'firm_office_phone',
            ]);
        });
    }
};

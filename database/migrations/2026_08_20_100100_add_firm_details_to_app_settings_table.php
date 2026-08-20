<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // The firm's own identity — the FROM block on an angadiya slip, and what
            // quotations and invoices will need later.
            $table->string('firm_name', 100)->nullable()->after('app_name');
            $table->string('firm_city', 100)->nullable()->after('firm_name');
            $table->string('firm_phone', 30)->nullable()->after('firm_city');

            // Angadiya A4 sheet layout.
            $table->unsignedTinyInteger('angadiya_columns')->default(3)->after('firm_phone');
            $table->decimal('angadiya_slip_height_mm', 5, 2)->default(45)->after('angadiya_columns');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'firm_name', 'firm_city', 'firm_phone',
                'angadiya_columns', 'angadiya_slip_height_mm',
            ]);
        });
    }
};

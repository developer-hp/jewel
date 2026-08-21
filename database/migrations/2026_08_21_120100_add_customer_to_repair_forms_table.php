<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_forms', function (Blueprint $table) {
            // Who this repair belongs to. The form keeps its own copy of the name,
            // number and address regardless — editing a customer must not rewrite a
            // form that has already printed.
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_forms', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};

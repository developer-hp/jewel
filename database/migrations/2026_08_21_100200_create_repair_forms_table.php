<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_forms', function (Blueprint $table) {
            $table->id();
            // Issued from the counter in app_settings; printed as "RG 204".
            $table->unsignedInteger('ref_no')->unique();

            $table->date('form_date');
            $table->date('delivery_date');

            $table->string('customer_name', 150);
            // Two boxes on the intake form: the number given, and a second one.
            $table->string('contact_no', 30);
            $table->string('contact_no_alt', 30)->nullable();
            $table->text('address')->nullable();

            $table->decimal('approx_extra_charge', 12, 2)->nullable();
            $table->text('remarks')->nullable();

            $table->string('photo_path')->nullable();
            $table->string('photo_disk', 30)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_forms');
    }
};

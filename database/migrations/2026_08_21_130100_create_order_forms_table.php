<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_forms', function (Blueprint $table) {
            $table->id();
            // Issued from the counter in app_settings; printed as "CF 160".
            $table->unsignedInteger('ref_no')->unique();

            // Who ordered. The columns below are the record of what was taken down;
            // this only ties the form to the register, and drives Other Orders.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->date('form_date');
            $table->date('delivery_date');

            $table->string('customer_name', 150);
            $table->string('contact_no', 30);
            $table->string('contact_no_alt', 30)->nullable();
            $table->text('address')->nullable();

            // Snapshotted, so a rename in the master cannot rewrite a printed form.
            $table->foreignId('sales_person_id')->nullable()->constrained('sales_persons')->nullOnDelete();
            $table->string('sales_person_name', 100)->nullable();

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
        Schema::dropIfExists('order_forms');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_estimates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ref_no')->unique();
            $table->date('estimate_date');

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('contact_no', 30)->nullable();
            $table->text('address')->nullable();

            // Snapshotted, so a rename in the master cannot rewrite a printed estimate.
            $table->foreignId('sales_person_id')->nullable()->constrained('sales_persons')->nullOnDelete();
            $table->string('sales_person_name', 100)->nullable();

            // Where the lines were built from, when they came off an order.
            $table->foreignId('order_form_id')->nullable()->constrained()->nullOnDelete();

            // The old gold coming in against this purchase. Its document prints as a
            // further page; it does not touch these figures.
            $table->foreignId('og_estimate_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('gst_enabled')->default(false);
            // Snapshotted at save, so a later change to the rate leaves this alone.
            $table->decimal('gst_percent', 5, 2)->default(0);

            // Photos are opt-in: the print carries them only when this is set.
            $table->boolean('show_photo')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estimate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_estimates');
    }
};

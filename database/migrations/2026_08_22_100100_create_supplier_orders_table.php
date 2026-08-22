<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            // Issued from the counter in app_settings; printed bare, no prefix.
            $table->unsignedInteger('form_no')->unique();

            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_type_id')->nullable()->constrained()->nullOnDelete();
            // Snapshotted: a rename in either master must not rewrite a slip that has
            // already printed. The type is what prints in the ITEM CODE row.
            $table->string('supplier_name', 150);
            $table->string('order_type_name', 50)->nullable();

            // Free text by design — the customer order this relates to, in capitals,
            // deliberately not a foreign key and not format-checked.
            $table->string('order_form_ref', 30)->nullable();

            $table->date('order_date');
            $table->date('customer_delivery_date');
            $table->date('followup_date');

            $table->string('description', 255)->nullable();
            $table->string('size_pcs', 50)->nullable();
            $table->string('sample_desc', 255)->nullable();
            $table->decimal('order_weight', 10, 3)->nullable();
            $table->decimal('sample_weight', 10, 3)->nullable();
            $table->text('special_remarks')->nullable();

            $table->string('photo_path')->nullable();
            $table->string('photo_disk', 30)->nullable();

            // Pending until the karigar brings the work back.
            $table->timestamp('received_at')->nullable();

            // What the QR on the office copy carries. Opaque and unique, so a printed
            // slip is not a link anything can follow and reveals nothing about any
            // other order.
            $table->string('scan_token', 32)->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('followup_date');
            $table->index('customer_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
    }
};

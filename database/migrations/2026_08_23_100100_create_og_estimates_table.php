<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('og_estimates', function (Blueprint $table) {
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

            // "IN", "OUT" or an order form — one control on screen, and mutually
            // exclusive here too. Exactly one of the pair is ever set.
            $table->enum('direction', ['in', 'out'])->nullable();
            $table->foreignId('order_form_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estimate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('og_estimates');
    }
};

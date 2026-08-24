<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ref_no')->unique();
            $table->date('voucher_date');

            $table->foreignId('sales_person_id')->nullable()->constrained('sales_persons')->nullOnDelete();
            $table->string('sales_person_name', 100)->nullable();

            $table->enum('payment_mode', ['cash', 'cheque'])->default('cash');

            // Same three-way reference as the estimate: a direction, or an order the
            // amount is an advance against.
            $table->enum('direction', ['in', 'out'])->nullable();
            $table->foreignId('order_form_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description', 255);
            $table->decimal('amount', 14, 2)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('voucher_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};

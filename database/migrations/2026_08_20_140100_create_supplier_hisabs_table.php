<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_hisabs', function (Blueprint $table) {
            $table->id();
            $table->date('hisab_date')->index();

            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            // Snapshot of how the supplier reads on the slip. A rename later must not
            // rewrite a slip that has already printed.
            $table->string('supplier_label', 150);

            // What the supplier is owed: some fine gold, and some cash.
            $table->decimal('fine_baki', 12, 3)->default(0);
            $table->decimal('cash_baki', 14, 2)->default(0);

            // Written when the hisab is settled; null while it is still open.
            $table->decimal('rate_per_gram', 12, 4)->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_hisabs');
    }
};

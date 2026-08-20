<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('angadiyas', function (Blueprint $table) {
            $table->id();

            // A convenience for filling the recipient in, not the source of truth:
            // the three fields below are snapshotted so editing a supplier later
            // never rewrites a slip that has already gone out.
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 150);
            $table->string('city', 100);
            $table->string('mobile', 30);
            $table->decimal('insurance_amount', 12, 2);
            $table->string('remark', 500)->nullable();

            $table->timestamp('printed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('printed_at');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('angadiyas');
    }
};

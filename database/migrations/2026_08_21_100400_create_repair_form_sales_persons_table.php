<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A table rather than a plain pivot: the name is snapshotted so a later
        // rename in the master cannot rewrite a form that has already printed.
        Schema::create('repair_form_sales_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_form_id')->constrained()->cascadeOnDelete();
            // Named explicitly: the pluralizer would look for `sales_people`.
            $table->foreignId('sales_person_id')->nullable()->constrained('sales_persons')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_form_sales_persons');
    }
};

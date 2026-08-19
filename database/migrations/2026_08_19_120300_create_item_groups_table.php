<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // Item codes are "<prefix><zero-padded next_sequence>", counted per group.
            $table->string('prefix', 10)->unique();
            $table->unsignedTinyInteger('code_padding')->default(4);
            $table->unsignedInteger('next_sequence')->default(1);
            $table->foreignId('metal_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_groups');
    }
};

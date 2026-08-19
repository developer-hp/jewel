<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stone_masters', function (Blueprint $table) {
            $table->id();
            // Stones and diamonds share this table; two screens, one schema.
            $table->enum('kind', ['stone', 'diamond']);
            $table->string('name', 100);
            $table->string('code', 30)->nullable()->unique();
            // Diamond-oriented attributes; left null for plain stones.
            $table->string('shape', 50)->nullable();
            $table->string('quality', 50)->nullable();
            $table->string('colour', 50)->nullable();
            $table->string('size', 50)->nullable();
            $table->enum('rate_unit', ['piece', 'gram', 'carat', 'fixed'])->default('carat');
            $table->decimal('default_rate', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kind', 'name']);
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stone_masters');
    }
};

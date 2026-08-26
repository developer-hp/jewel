<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who gets the day opening's reports on WhatsApp.
 *
 * A short list of people rather than customers — the owner, the accountant — so it
 * is a master of its own and not a view over the customer register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_receivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('mobile', 30);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_receivers');
    }
};

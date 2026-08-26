<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the shop registered with Meta for each message the app can send.
 *
 * One row per App\Support\WhatsAppEvent case, created on demand rather than seeded:
 * an event with no row is one nobody has set up yet, which is exactly what a fresh
 * install should look like.
 *
 * The placeholders themselves are not stored — which value fills {{1}} is a code
 * decision, in App\Services\WhatsApp. This table holds only what the shop can
 * legitimately change without a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50)->unique();
            $table->string('name', 100);
            $table->string('language', 10)->default('en');
            // Off until someone says otherwise. Every message is billed, the
            // template has to clear Meta's review first, and — less obviously —
            // the whole existing test suite creates orders: were this to default
            // on, every one of those would try to reach the Cloud API.
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};

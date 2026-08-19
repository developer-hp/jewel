<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row branding settings, read through AppSetting::current().
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();

            $table->string('app_name', 60)->default('Jewel');

            // Paths on the `public` disk; null falls back to the bundled theme logo.
            $table->string('logo_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('logo_small_path')->nullable();

            // The sidebar user panel, which the theme ships as a background photo.
            $table->string('sidebar_user_bg_from', 20)->default('#0acf97');
            $table->string('sidebar_user_bg_to', 20)->default('#39afd1');
            $table->string('sidebar_user_text_color', 20)->default('#ffffff');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};

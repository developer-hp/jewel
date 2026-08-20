<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // One active session per user; a second sign-in must resolve the clash.
            $table->boolean('single_device_login')->default(false)->after('media_disk');
            // 0 disables idle logout entirely.
            $table->unsignedSmallInteger('idle_timeout_minutes')->default(0)->after('single_device_login');
            // How long the "about to be logged out" warning is shown for.
            $table->unsignedSmallInteger('idle_warning_seconds')->default(60)->after('idle_timeout_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['single_device_login', 'idle_timeout_minutes', 'idle_warning_seconds']);
        });
    }
};

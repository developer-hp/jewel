<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Path on the configured media disk, plus the disk it was written to, so
            // photos stored before a local -> S3 switch still resolve afterwards.
            $table->string('photo_path')->nullable()->after('description');
            $table->string('photo_disk', 20)->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_disk']);
        });
    }
};

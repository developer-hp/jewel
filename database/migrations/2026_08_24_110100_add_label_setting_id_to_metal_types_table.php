<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which label template a metal type prints with.
 *
 * Null means "use the default", which is what every metal type starts as — so
 * nothing changes until someone chooses otherwise.
 *
 * nullOnDelete is a backstop, not the policy: LabelSettingController refuses to
 * delete a template a metal type is using, the same way every other master in this
 * app refuses a delete with dependents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_types', function (Blueprint $table) {
            $table->foreignId('label_setting_id')->nullable()->after('is_active')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('label_setting_id');
        });
    }
};

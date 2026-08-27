<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public landing page: what it shows, and which rates it is allowed to publish.
 *
 * The rate flag lives on the purity rather than as a list of ids in app_settings,
 * mirroring item_groups.show_in_daily_report. A column cannot go stale when a purity
 * is deleted, and it defaults to false — a purity added later must never appear on a
 * public page until somebody says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purities', function (Blueprint $table) {
            $table->boolean('show_on_landing')->default(false)->after('is_active');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            // Off by default, so `/` keeps redirecting to login until the shop has
            // actually filled the page in.
            $table->boolean('landing_enabled')->default(false);
            $table->string('landing_announcement', 255)->nullable();
            $table->string('landing_rate_note', 20)->nullable()->default('+GST');
            // One number per line, like repair_terms. Falls back to the firm phones.
            $table->text('landing_phones')->nullable();
            $table->text('firm_address')->nullable();

            $table->string('payment_qr_path')->nullable();

            $table->string('social_facebook', 200)->nullable();
            $table->string('social_instagram', 200)->nullable();
            $table->string('social_youtube', 200)->nullable();
            $table->string('social_whatsapp', 200)->nullable();
            $table->string('social_x', 200)->nullable();
            $table->string('social_linkedin', 200)->nullable();

            $table->string('bank_ac_no', 40)->nullable();
            $table->string('bank_ac_name', 150)->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('bank_ac_type', 30)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_swift_code', 20)->nullable();
            $table->string('bank_purpose_code', 20)->nullable();
            $table->string('bank_upi_id', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purities', function (Blueprint $table) {
            $table->dropColumn('show_on_landing');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'landing_enabled', 'landing_announcement', 'landing_rate_note',
                'landing_phones', 'firm_address', 'payment_qr_path',
                'social_facebook', 'social_instagram', 'social_youtube',
                'social_whatsapp', 'social_x', 'social_linkedin',
                'bank_ac_no', 'bank_ac_name', 'bank_ifsc', 'bank_branch',
                'bank_ac_type', 'bank_name', 'bank_swift_code',
                'bank_purpose_code', 'bank_upi_id',
            ]);
        });
    }
};

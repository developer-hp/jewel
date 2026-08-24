<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the single label setting into a set of named templates.
 *
 * The existing row becomes "Standard Tag", flagged as the default, so every tag
 * printed before this migration keeps printing exactly as it did.
 *
 * `layout` is a plain string, not an enum, even though `qr_content` next to it is
 * one: a fourth layout would otherwise mean an ALTER TABLE ... MODIFY on a live
 * table. LabelSetting::LAYOUTS is the list, and the form request validates against
 * it.
 *
 * Nothing is INSERTed here. Tests run on a freshly migrated database, so a row
 * created by a migration would exist in every one of them; the template is created
 * on demand by LabelSetting::default() instead, which is also what lets a fresh
 * install print before anyone opens the settings screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_settings', function (Blueprint $table) {
            // Nullable to begin with — the existing row has no name yet.
            $table->string('name', 60)->nullable()->after('id');
            $table->string('layout', 20)->default('standard')->after('name');
            $table->boolean('is_default')->default(false)->after('layout');

            // What the two detail layouts need. Defaulting these on leaves the
            // standard tag unchanged, since it ignores them.
            $table->boolean('show_making_charge')->default(true)->after('show_huid');
            $table->boolean('show_item_name')->default(true)->after('show_making_charge');
            $table->boolean('show_stone_rate')->default(true)->after('show_diamond');
            $table->boolean('show_oc')->default(true)->after('show_extra_charges');

            // How many stone rows a detail tag prints before the rest collapse into
            // one line. Beyond this the tag runs onto a second page.
            $table->unsignedTinyInteger('max_stone_rows')->default(6)->after('font_size_pt');
        });

        $this->backfill();

        Schema::table('label_settings', function (Blueprint $table) {
            $table->string('name', 60)->nullable(false)->change();
            $table->unique('name');
        });
    }

    /**
     * Name the rows that predate this migration. On a fresh install the table is
     * empty and this does nothing.
     */
    private function backfill(): void
    {
        $ids = DB::table('label_settings')->orderBy('id')->pluck('id');

        foreach ($ids as $position => $id) {
            DB::table('label_settings')->where('id', $id)->update([
                // The first row is the one the app has been printing from.
                'name' => $position === 0 ? 'Standard Tag' : "Tag {$id}",
                'layout' => 'standard',
                'is_default' => $position === 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('label_settings', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn([
                'name', 'layout', 'is_default',
                'show_making_charge', 'show_item_name', 'show_stone_rate', 'show_oc',
                'max_stone_rows',
            ]);
        });
    }
};

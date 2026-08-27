<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One saved note-count per user, for the cash calculator in the topbar.
 *
 * Per user rather than per drawer: this is a scratchpad for whoever is counting the
 * till, not a record of what was in it. Two people counting at once must not
 * overwrite each other, and nothing here is a document — the cash entries are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_calculators', function (Blueprint $table) {
            $table->id();
            // Unique: one scratchpad each, replaced in place rather than accumulated.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // { "counter": {"500": 13, ...}, "safe": {"500": 1400, ...} }
            $table->json('counts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_calculators');
    }
};

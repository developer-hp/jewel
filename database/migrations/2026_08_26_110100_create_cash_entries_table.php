<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money taken or paid at the counter, settling one document.
 *
 * The document is an item estimate OR a voucher, held in two nullable foreign keys
 * rather than a polymorphic pair. Nothing in this app is polymorphic — ItemEstimate
 * already carries order_form_id and og_estimate_id side by side — and real foreign
 * keys mean "consumed once" is a plain unique index rather than a two-column one
 * over an untyped id.
 *
 * Exactly one of item_estimate_id and voucher_id is ever set. MySQL could express
 * that as a CHECK, but this schema uses none; CashEntryRequest enforces it and
 * CashEntry::splitDocumentReference() can only ever produce one.
 *
 * Everything the document says is snapshotted here. ItemEstimate::summary() and
 * OgEstimate::totals() both recompute from live lines, and refPrefix() reads
 * app_settings — without the snapshots, editing an estimate or renaming a prefix
 * would silently rewrite cash that has already been counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ref_no')->unique();
            $table->date('entry_date')->index();

            // A drawer with entries must not vanish underneath them.
            $table->foreignId('cash_drawer_id')->constrained()->restrictOnDelete();

            $table->foreignId('item_estimate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('og_estimate_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('cash_event', ['in', 'out'])->default('in');

            $table->decimal('final_amount', 14, 2)->default(0);
            $table->string('document_reference', 30);
            $table->string('party_name', 150)->nullable();

            $table->decimal('cash_amount', 14, 2)->default(0);
            $table->decimal('cheque_amount', 14, 2)->default(0);
            $table->string('cheque_number', 50)->nullable();
            $table->string('cheque_name', 150)->nullable();
            $table->string('cheque_mobile', 30)->nullable();
            $table->string('cheque_bank', 100)->nullable();

            $table->decimal('gold_weight', 12, 3)->default(0);
            $table->decimal('gold_amount', 14, 2)->default(0);
            $table->string('og_reference', 30)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // A document may be settled once, and deleting an entry must release it.
            //
            // deleted_at cannot be the second half of that index directly: MySQL
            // treats NULLs in a unique index as distinct, so two live rows both
            // carrying NULL would not collide and the index would enforce nothing.
            // This stands in for it with a value, and the indexes pair each document
            // against that instead. An unreferenced document is still NULL in its own
            // column, so a voucher-only entry never collides on item_estimate_id.
            //
            // The remaining hole: the marker has whole-second precision, so
            // soft-deleting two entries against the same document inside one second
            // collides. CashEntryRequest catches every realistic case; this is the
            // backstop, the role ref_no's unique plays for the counter.
            $table->dateTime('deleted_marker')
                ->storedAs("COALESCE(deleted_at, '1970-01-01 00:00:00')");

            $table->unique(['item_estimate_id', 'deleted_marker']);
            $table->unique(['voucher_id', 'deleted_marker']);
            $table->unique(['og_estimate_id', 'deleted_marker']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
    }
};

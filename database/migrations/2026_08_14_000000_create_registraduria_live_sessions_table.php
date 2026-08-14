<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per document_number claims that a genuine (already-paid) 2captcha/live
     * lookup is currently in flight for that cédula — inserted right before any
     * `$adapter->startLookup($cedula)` call and deleted once a definitive outcome is
     * known (success, genuine failure, or the interactive modal's own fast-path
     * handler). The unique index on document_number is the actual concurrency guard:
     * a second attempt (a racing cron job, a refreshed/reopened interactive modal, a
     * repeated force-refresh click) fails to insert and is turned away BEFORE it ever
     * calls startLookup() again, so the same cédula is never paid for twice at the
     * same time. `expires_at` bounds how long a row can block re-attempts if nothing
     * ever explicitly releases it (crash, abandoned browser tab, etc).
     *
     * See .planning/debug/resolved/2captcha-duplicate-spend.md.
     */
    public function up(): void
    {
        Schema::create('registraduria_live_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('session_id')->nullable();
            $table->string('adapter_class')->nullable();
            $table->foreignId('voter_id')->nullable()->constrained('voters')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('resolved_via')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registraduria_live_sessions');
    }
};

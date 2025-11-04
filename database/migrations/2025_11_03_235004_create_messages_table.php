<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('message_batches')->nullOnDelete();
            $table->string('type'); // birthday, reminder, custom, campaign
            $table->string('channel'); // whatsapp, sms, email
            $table->string('subject')->nullable(); // Para email
            $table->text('content');
            $table->string('status')->default('pending'); // pending, scheduled, sent, failed, delivered, read, clicked
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('external_id')->nullable(); // ID del proveedor externo
            $table->json('metadata')->nullable(); // Tracking adicional
            $table->timestamps();

            // Índices
            $table->index('campaign_id');
            $table->index('voter_id');
            $table->index('type');
            $table->index('channel');
            $table->index('status');
            $table->index('scheduled_for');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

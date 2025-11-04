<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // birthday, reminder, custom, campaign
            $table->string('channel'); // whatsapp, sms, email
            $table->string('subject')->nullable(); // Para email
            $table->text('content'); // Con variables: {{nombre}}, {{edad}}, {{candidato}}, etc.
            $table->boolean('is_active')->default(true);

            // Control Anti-Spam
            $table->integer('max_per_voter_per_day')->default(3);
            $table->integer('max_per_campaign_per_hour')->default(100);

            // Horarios Permitidos
            $table->time('allowed_start_time')->default('08:00:00');
            $table->time('allowed_end_time')->default('20:00:00');
            $table->json('allowed_days')->nullable(); // ['monday', 'tuesday', ...]

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Índices
            $table->index('campaign_id');
            $table->index('type');
            $table->index('channel');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};

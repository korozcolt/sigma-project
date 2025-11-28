<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('election_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['simulation', 'real'])->default('simulation');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('simulation_number')->nullable();
            $table->text('notes')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->index(['campaign_id', 'type']);
            $table->index(['campaign_id', 'is_active']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('election_events');
    }
};

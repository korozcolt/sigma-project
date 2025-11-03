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
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable(); // FK se agregará en FASE 2
            $table->string('name');
            $table->boolean('is_global')->default(false);
            $table->timestamps();

            // Un barrio con el mismo nombre puede existir por municipio y campaña
            $table->unique(['municipality_id', 'name', 'campaign_id']);
            $table->index('campaign_id'); // Índice para preparar FK futura
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};

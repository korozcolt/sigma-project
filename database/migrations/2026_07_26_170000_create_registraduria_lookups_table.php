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
        Schema::create('registraduria_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('puesto_nombre')->nullable();
            $table->string('puesto_codigo')->nullable();
            $table->string('zona_codigo')->nullable();
            $table->string('mesa_numero')->nullable();
            $table->string('departamento')->nullable();
            $table->string('municipio')->nullable();
            $table->string('direccion')->nullable();
            $table->string('source')->default('live');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registraduria_lookups');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_places', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('dane_department_code');
            $table->unsignedSmallInteger('dane_municipality_code');
            $table->unsignedSmallInteger('zone_code');
            $table->unsignedSmallInteger('place_code');

            $table->string('name');
            $table->string('address')->nullable();
            $table->string('commune')->nullable();
            $table->unsignedSmallInteger('max_tables');

            $table->timestamps();

            $table->unique(['dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code'], 'polling_places_divipole_unique');
            $table->index(['municipality_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_places');
    }
};


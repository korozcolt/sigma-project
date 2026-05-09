<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polling_places', function (Blueprint $table): void {
            $table->dropUnique('polling_places_divipole_unique');
            $table->unsignedSmallInteger('dane_department_code')->nullable()->change();
            $table->unsignedSmallInteger('dane_municipality_code')->nullable()->change();
            $table->unique(['municipality_id', 'zone_code', 'place_code'], 'polling_places_muni_zone_place_unique');
        });
    }

    public function down(): void
    {
        Schema::table('polling_places', function (Blueprint $table): void {
            $table->dropUnique('polling_places_muni_zone_place_unique');
            $table->unsignedSmallInteger('dane_department_code')->nullable(false)->change();
            $table->unsignedSmallInteger('dane_municipality_code')->nullable(false)->change();
            $table->unique(['dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code'], 'polling_places_divipole_unique');
        });
    }
};

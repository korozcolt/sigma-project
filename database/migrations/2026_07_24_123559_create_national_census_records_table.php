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
        Schema::create('national_census_records', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->foreignId('polling_place_id')->nullable()->constrained('polling_places')->nullOnDelete();
            $table->unsignedSmallInteger('dane_department_code');
            $table->unsignedSmallInteger('dane_municipality_code');
            $table->unsignedSmallInteger('zone_code');
            $table->unsignedSmallInteger('place_code');
            $table->string('polling_station_name')->nullable();
            $table->string('table_number')->nullable();
            $table->timestamp('imported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('national_census_records');
    }
};

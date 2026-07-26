<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real live Registraduría lookups never carry a "CODIGO PUESTO"/"ZONA" column
     * (see RegistraduriaService::parseConsultaHtml()), so zone_code/place_code are
     * always blank for a freshly created, live-sourced polling place. NOT NULL forced
     * downstream code to insert '' into these unsignedSmallInteger columns, throwing
     * MySQL error 1366. Making them nullable lets live-sourced puestos be stored with
     * NULL codes (matched/deduplicated by name instead — see PollingPlaceResolver).
     *
     * MySQL's unique index treats multiple NULLs as distinct rows, so this does not
     * relax the real DIVIPOLE-code uniqueness guarantee for rows that do have codes.
     */
    public function up(): void
    {
        Schema::table('polling_places', function (Blueprint $table): void {
            $table->unsignedSmallInteger('zone_code')->nullable()->change();
            $table->unsignedSmallInteger('place_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('polling_places', function (Blueprint $table): void {
            $table->unsignedSmallInteger('zone_code')->nullable(false)->change();
            $table->unsignedSmallInteger('place_code')->nullable(false)->change();
        });
    }
};

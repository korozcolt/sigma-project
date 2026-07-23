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
        Schema::table('voters', function (Blueprint $table) {
            $table->foreignId('gremio_id')->nullable()->after('notes')->constrained()->nullOnDelete();
            $table->foreignId('subcategoria_id')->nullable()->after('gremio_id')->constrained()->nullOnDelete();
            $table->string('lugar_expedicion_cedula')->nullable()->after('subcategoria_id');
            $table->string('placa')->nullable()->after('lugar_expedicion_cedula');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gremio_id');
            $table->dropConstrainedForeignId('subcategoria_id');
            $table->dropColumn(['lugar_expedicion_cedula', 'placa']);
        });
    }
};

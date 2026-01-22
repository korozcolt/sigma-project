<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->foreignId('polling_place_id')->nullable()->after('neighborhood_id')->constrained('polling_places')->nullOnDelete();
            $table->unsignedSmallInteger('polling_table_number')->nullable()->after('polling_place_id');

            $table->index('polling_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('polling_place_id');
            $table->dropColumn('polling_table_number');
        });
    }
};


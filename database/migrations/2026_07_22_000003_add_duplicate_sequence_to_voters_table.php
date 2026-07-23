<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->unsignedInteger('duplicate_sequence')->default(0)->after('document_number');
        });

        Schema::table('voters', function (Blueprint $table) {
            $table->dropUnique(['document_number']);
            $table->unique(['document_number', 'duplicate_sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropUnique(['document_number', 'duplicate_sequence']);
            $table->unique('document_number');
            $table->dropColumn('duplicate_sequence');
        });
    }
};

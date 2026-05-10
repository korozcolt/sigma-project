<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('census_records', function (Blueprint $table): void {
            $table->string('full_name')->nullable()->default(null)->change();
            $table->string('municipality_code')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('census_records', function (Blueprint $table): void {
            $table->string('full_name')->nullable(false)->change();
        });
    }
};

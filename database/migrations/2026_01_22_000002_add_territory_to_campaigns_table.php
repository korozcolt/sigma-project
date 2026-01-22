<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('scope')->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->after('department_id')->constrained()->nullOnDelete();

            $table->index('department_id');
            $table->index('municipality_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};


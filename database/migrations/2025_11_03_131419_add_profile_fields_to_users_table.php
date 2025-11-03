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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('secondary_phone')->nullable()->after('phone');
            $table->string('document_number')->nullable()->unique()->after('secondary_phone');
            $table->date('birth_date')->nullable()->after('document_number');
            $table->text('address')->nullable()->after('birth_date');
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete()->after('address');
            $table->foreignId('neighborhood_id')->nullable()->constrained()->nullOnDelete()->after('municipality_id');
            $table->string('profile_photo_path')->nullable()->after('neighborhood_id');

            $table->index('document_number');
            $table->index('municipality_id');
            $table->index('neighborhood_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['municipality_id']);
            $table->dropForeign(['neighborhood_id']);
            $table->dropColumn([
                'phone',
                'secondary_phone',
                'document_number',
                'birth_date',
                'address',
                'municipality_id',
                'neighborhood_id',
                'profile_photo_path',
            ]);
        });
    }
};

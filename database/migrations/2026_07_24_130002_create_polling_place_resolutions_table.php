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
        Schema::create('polling_place_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voter_id')->constrained()->cascadeOnDelete();
            $table->string('previous_source')->nullable();
            $table->string('new_source');
            $table->foreignId('polling_place_id')->nullable()->constrained('polling_places')->nullOnDelete();
            $table->string('table_number')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolved_via');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('voter_id');
            $table->index('new_source');
            $table->index('resolved_via');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polling_place_resolutions');
    }
};

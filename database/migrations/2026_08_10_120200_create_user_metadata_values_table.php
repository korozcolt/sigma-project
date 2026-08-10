<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_metadata_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metadata_key_id')->constrained('metadata_keys')->restrictOnDelete();
            $table->string('value');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index(['user_id', 'metadata_key_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_metadata_values');
    }
};

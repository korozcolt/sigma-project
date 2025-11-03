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
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('document_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->string('phone');
            $table->string('secondary_phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->text('detailed_address')->nullable();
            $table->foreignId('registered_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending_review');
            $table->timestamp('census_validated_at')->nullable();
            $table->timestamp('call_verified_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('voted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campaign_id', 'document_number']);
            $table->index('status');
            $table->index('campaign_id');
            $table->index('municipality_id');
            $table->index('neighborhood_id');
            $table->index('registered_by');
            $table->index('document_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voters');
    }
};

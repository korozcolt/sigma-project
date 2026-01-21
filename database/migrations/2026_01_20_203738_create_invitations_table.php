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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->foreignId('invited_by_user_id')->constrained('users')->onDelete('cascade');
            $table->string('invited_email');
            $table->string('invited_name')->nullable();
            $table->enum('target_role', ['LEADER', 'COORDINATOR']);
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('municipality_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('parent_leader_id')->nullable()->comment('ID del líder que invita a votantes cuando es coordinador')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('registered_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['token', 'status']);
            $table->index(['invited_by_user_id', 'status']);
            $table->index(['target_role', 'status']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};

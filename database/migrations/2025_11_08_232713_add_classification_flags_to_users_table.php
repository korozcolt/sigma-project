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
            $table->boolean('is_vote_recorder')
                ->default(false)
                ->after('email_verified_at')
                ->comment('Indica si este usuario es un anotador (registra votos el día D)');

            $table->boolean('is_witness')
                ->default(false)
                ->after('is_vote_recorder')
                ->comment('Indica si este usuario es un testigo electoral');

            $table->string('witness_assigned_station')
                ->nullable()
                ->after('is_witness')
                ->comment('Mesa electoral asignada al testigo');

            $table->decimal('witness_payment_amount', 10, 2)
                ->nullable()
                ->after('witness_assigned_station')
                ->comment('Monto a pagar al testigo electoral');

            $table->boolean('is_special_coordinator')
                ->default(false)
                ->after('witness_payment_amount')
                ->comment('Indica si este coordinador es especial (concejal, senador, etc.)');

            // Índices para consultas frecuentes
            $table->index('is_vote_recorder');
            $table->index('is_witness');
            $table->index('is_special_coordinator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_vote_recorder']);
            $table->dropIndex(['is_witness']);
            $table->dropIndex(['is_special_coordinator']);

            $table->dropColumn([
                'is_vote_recorder',
                'is_witness',
                'witness_assigned_station',
                'witness_payment_amount',
                'is_special_coordinator',
            ]);
        });
    }
};

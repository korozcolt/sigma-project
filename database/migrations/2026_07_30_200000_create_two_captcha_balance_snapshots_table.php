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
        Schema::create('two_captcha_balance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->decimal('balance', 12, 5); // USD, ej. 0.93958
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_captcha_balance_snapshots');
    }
};

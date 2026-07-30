<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwoCaptchaBalanceSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\TwoCaptchaBalanceSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'balance',
        'checked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:5',
            'checked_at' => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks an in-flight (already-paid) live Registraduría/2captcha lookup for a single
 * cédula. See the creating migration's docblock and
 * .planning/debug/resolved/2captcha-duplicate-spend.md for the full rationale — this
 * table is simultaneously (1) a distributed claim/lock (unique document_number) that
 * prevents concurrent duplicate 2captcha spend, and (2) the breadcrumb
 * CollectRegistraduriaLookupResult uses to keep checking for an already-paid-for
 * result that the synchronous ~40s cascade window gave up on too early.
 */
class RegistraduriaLiveSession extends Model
{
    /** @use HasFactory<\Database\Factories\RegistraduriaLiveSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'document_number',
        'session_id',
        'adapter_class',
        'voter_id',
        'campaign_id',
        'resolved_via',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}

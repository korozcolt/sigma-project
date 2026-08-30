<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Municipality extends Model
{
    /** @use HasFactory<\Database\Factories\MunicipalityFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'department_id',
        'name',
        'code',
    ];

    /**
     * Get the department that owns the municipality.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Find a Municipality by name, tolerating the formatting variance real live
     * Registraduría results carry (accents, extra punctuation, parenthetical alternate
     * names like "COLOSO (RICAURTE)") that an exact LOWER(name) comparison misses. Tries
     * the cheap exact/indexed match first; only normalizes and scans the full (small,
     * ~1100-row) catalog when the exact match fails. Never guesses when normalization
     * produces more than one candidate (e.g. homonymous municipalities in different
     * departments, like the many "Granada"/"Sucre"/"Bolívar" municipality names that
     * already existed in the catalog before this normalization) — returns null and logs
     * instead, since picking the wrong one would be worse than not resolving at all.
     * See .planning/debug/resolved/apoyo-marcado-en-vivo-con-puesto-sin-resolver.md.
     */
    public static function findByFuzzyName(string $rawName): ?self
    {
        if (blank($rawName)) {
            return null;
        }

        $exact = self::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($rawName)])
            ->first();

        if ($exact) {
            return $exact;
        }

        $normalized = self::normalizeName($rawName);

        if ($normalized === '') {
            return null;
        }

        $candidates = self::all()->filter(
            fn (self $municipality): bool => self::normalizeName($municipality->name) === $normalized
        );

        if ($candidates->count() !== 1) {
            if ($candidates->count() > 1) {
                Log::warning('municipality.fuzzy_match_ambiguous', [
                    'raw_name' => $rawName,
                    'normalized' => $normalized,
                    'candidate_ids' => $candidates->pluck('id')->all(),
                ]);
            }

            return null;
        }

        return $candidates->first();
    }

    /**
     * Strips accents, parenthetical suffixes, and all punctuation/whitespace, then
     * uppercases — so "TOLUVIEJO", "Tolú Viejo", and "TOLU VIEJO" all normalize to the
     * same key, and "COLOSO (RICAURTE)" normalizes to match "Coloso".
     */
    public static function normalizeName(string $name): string
    {
        $name = Str::ascii($name);
        $name = preg_replace('/\([^)]*\)/', '', $name) ?? $name;
        $name = preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? $name;

        return strtoupper($name);
    }

    /**
     * Get the neighborhoods for the municipality.
     */
    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class);
    }

    /**
     * Get the voters registered in this municipality.
     */
    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class);
    }
}

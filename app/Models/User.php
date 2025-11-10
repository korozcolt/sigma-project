<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'secondary_phone',
        'document_number',
        'birth_date',
        'address',
        'municipality_id',
        'neighborhood_id',
        'profile_photo_path',
        'is_vote_recorder',
        'is_witness',
        'witness_assigned_station',
        'witness_payment_amount',
        'is_special_coordinator',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'is_vote_recorder' => 'boolean',
            'is_witness' => 'boolean',
            'is_special_coordinator' => 'boolean',
            'witness_payment_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the municipality where the user is located
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Get the neighborhood where the user is located
     */
    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    /**
     * Get the voter profile for this user (coordinadores y líderes tienen su propio registro como votante)
     */
    public function voter(): HasOne
    {
        return $this->hasOne(Voter::class);
    }

    /**
     * Get the voters directly registered by this user
     * Alias de registeredVoters() para mayor claridad
     */
    public function directVoters(): HasMany
    {
        return $this->registeredVoters();
    }

    /**
     * Get the campaigns this user is part of
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_user')
            ->withPivot('role_id', 'assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Get the campaigns created by this user
     */
    public function createdCampaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'created_by');
    }

    /**
     * Get the territorial assignments for this user
     */
    public function territorialAssignments(): HasMany
    {
        return $this->hasMany(TerritorialAssignment::class);
    }

    /**
     * Get the voters registered by this user
     */
    public function registeredVoters(): HasMany
    {
        return $this->hasMany(Voter::class, 'registered_by');
    }

    /**
     * Scope query to only include vote recorders
     */
    public function scopeVoteRecorders($query)
    {
        return $query->where('is_vote_recorder', true);
    }

    /**
     * Scope query to only include witnesses
     */
    public function scopeWitnesses($query)
    {
        return $query->where('is_witness', true);
    }

    /**
     * Scope query to only include special coordinators
     */
    public function scopeSpecialCoordinators($query)
    {
        return $query->where('is_special_coordinator', true);
    }

    /**
     * Scope query to only include assigned witnesses (with station)
     */
    public function scopeAssignedWitnesses($query)
    {
        return $query->where('is_witness', true)
            ->whereNotNull('witness_assigned_station');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'account_status',
        'frozen_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'password' => 'hashed',
            'frozen_until' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function isFrozen(): bool
    {
        if ($this->account_status !== 'frozen') {
            return false;
        }
        if ($this->frozen_until !== null && $this->frozen_until->isPast()) {
            return false;
        }
        return true;
    }

    public function isBlacklisted(): bool
    {
        return $this->account_status === 'blacklisted';
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'uploaded_by');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function playHistory(): HasMany
    {
        return $this->hasMany(PlayHistory::class);
    }

    public function recommendationScores(): HasMany
    {
        return $this->hasMany(RecommendationScore::class);
    }
}

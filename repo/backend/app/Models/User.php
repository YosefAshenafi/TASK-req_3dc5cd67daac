<?php

namespace App\Models;

use App\Casts\EncryptedField;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'role',
        'email_enc',
        'frozen_until',
        'blacklisted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frozen_until'    => 'datetime',
            'blacklisted_at'  => 'datetime',
            'email_enc'       => EncryptedField::class,
            'password'        => 'hashed',
        ];
    }

    /**
     * Override the auth identifier to use username.
     */
    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    // Relationships

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'uploaded_by');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class, 'owner_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'user_id');
    }

    public function playHistory(): HasMany
    {
        return $this->hasMany(PlayHistory::class, 'user_id');
    }

    public function playlistShares(): HasMany
    {
        return $this->hasMany(PlaylistShare::class, 'created_by');
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const SUPER_ADMIN_EMAIL = 'karlsteven.maddela@dict.gov.ph';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isSuperAdmin(): bool
    {
        return mb_strtolower($this->email) === self::SUPER_ADMIN_EMAIL;
    }

    public function lectures(): HasMany
    {
        return $this->hasMany(Lecture::class, 'creator_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'creator_id');
    }

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
        ];
    }
}

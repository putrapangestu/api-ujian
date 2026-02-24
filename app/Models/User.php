<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * User Model
 *
 * Model ini merepresentasikan tabel users dalam database
 * Digunakan untuk authentication dan management user
 *
 * @package App\Models
 * @author Development Team
 * @version 1.0.0
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string|null $avatar
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuids, HasRoles;

    /**
     * Role constants
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
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
        ];
    }

    /**
     * Check if user is admin
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is regular user
     *
     * @return bool
     */
    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Get user exams relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userExams()
    {
        return $this->hasMany(UserExam::class);
    }

    /**
     * Get avatar URL
     *
     * @return string
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? asset('storage/' . $this->avatar)
            : asset('images/default-avatar.png');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'trainer_id',
        'phone',
        'class',
        'generation',
        'province',
        'gender',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // student -> trainer
    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    // trainer -> students
    public function students()
    {
        return $this->hasMany(User::class, 'trainer_id');
    }

    // Check user role
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTrainer(): bool
    {
        return $this->role === 'trainer';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }
}

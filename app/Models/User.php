<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'gender',
        'student_id', 
        'generation', 
        'class_name', 
        'phone',
        'province', 
        'email', 
        'password', 
        'role', 
        'avatar_id', 
        'trainer_id',
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

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class);
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function students()
    {
        return $this->hasMany(User::class, 'trainer_id');
    }

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

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}

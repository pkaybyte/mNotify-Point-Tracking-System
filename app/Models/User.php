<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'total_verified_points'
    ];

    public function pointsGiven(){
        return $this->hasMany(PointAssignment::class, 'assignor_id');
    }

    public function pointsRecieved(){
        return $this->hasMany(PointAssignment::class, 'recipient_id');
    }

    public function pendingPoints(){
        return $this->pointsRecieved()->where('status', 'pending');
    }

    public function verifiedPoints(){
        return $this->pointsRecieved()->where('status', 'verified');
    }

    public function canVerifyPoints(): bool{
        return $this->role == 'supervisor';
    }

    public function canAssignPoints(): bool{
        return in_array($this->role, ['user', 'supervisor']);
    }

    public function isAdmin():bool {
        return $this->role == 'admin';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
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
}
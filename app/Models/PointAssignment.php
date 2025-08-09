<?php

namespace App\Models;

use GuzzleHttp\Psr7\Query;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PointAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignor_id',
        'recipient_id',
        'points',
        'reason',
        'verified_by',
        'verified_at',
        'is_bulk_assignment'
    ];

    protected $casts = [
        'verified_by' => 'datetime',
        'verified_at' => 'datetime',
        'is_bulk_assignment' => 'boolean'
    ];

    //A point is assigned by a user
    public function fromUser(){
        return $this->belongsTo(User::class, 'assignor_id');
    }

    //A point is recieved from a user
    public function toUser(){
        return $this->belongsTo(User::class, 'recipient_id');
    }

    //Point verifier
    public function verifier(){
        return $this-> belongsTo(User::class, 'verified_by');
    }

    //Status functions
    public function scopePending($query){
        return $this->where('status', 'pending');
    }

    public function scopeVerified($query){
        return $this->where('status', 'verified');
    }
}

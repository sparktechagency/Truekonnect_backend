<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        'role',
        'status',
        'referral_id',
        'referral_code',
        'balance',
        'country_id',
        'verification_by',
        'rejection_reason',
        'otp',
        'otp_expires_at',
        'avatar',
        'earn_token',
        'convert_token'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role'=>$this->role,
        ];
    }

    public function country(){
        return $this->belongsTo(Countrie::class,'country_id');
    }

    public function referral(){
        return $this->belongsTo(User::class,'referral_id');
    }

    public function verifiedAccounts()
    {
        return $this->belongsTo(User::class, 'verification_by');
    }
    public function verifiedTasks(){
        return $this->hasMany(Task::class, 'verified_by');
    }
    public function verifiedPerformance(){
        return $this->hasMany(TaskPerformer::class, 'verified_by');
    }

}

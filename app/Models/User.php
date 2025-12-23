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
        'withdrawal_status',
        'verification_by',
        'rejection_reason',
        'otp',
        'otp_expires_at',
        'avatar',
        'earn_token',
        'ref_income',
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

    public function scopeSearch($query, $value)
    {
        if (!$value) {
            return $query;
        }

        return $query->where(function ($q) use ($value) {
            $q->where('name', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%")
//                ->orWhere('status', 'like', "%{$value}%")
                ->orWhere('role', 'like', "%{$value}%");
        });
    }

    public function scopePerformerOrBrand($query, $status = null)
    {
        $query->whereIn('role', ['performer', 'brand']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
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

    public function taskPerformerSocialAc()
    {
        return $this->hasMany(SocialAccount::class, 'user_id');
    }

    public function taskSave(){
        return $this->hasMany(TaskSave::class, 'user_id');
    }

    public function getStatusAttribute($value)
    {
        if ($value == 'active') {
            return 'Not Banned';
        }else{
            return 'Banned';
        }
    }

    public function getAvatarAttribute($value)
    {
        if ($value == 0) {
           return 'avatars/default_avatar.png';
        }
        return $value;
    }



}

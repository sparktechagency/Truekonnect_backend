<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    protected $fillable=['user_id','sm_id',
        'profile_name',
        'profile_image',
        'note',
        'verification_by',
        'rejection_reason',
        'status',
    ];
    public function social()
    {
        return $this->belongsTo(SocialMedia::class, 'sm_id');
    }
    public  function User(){
        return $this->belongsTo(User::class,'user_id');
    }
}

<?php

namespace App\Models;

use Google\Service\Dfareporting\Country;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable=['user_id','amount','status','trnx_id','message'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->hasManyThrough(Country::class,User::class,'id','id','user_id','country_id');
    }

}

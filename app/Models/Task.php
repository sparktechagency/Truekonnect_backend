<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable=['sm_id','sms_id','user_id','country_id','quantity','description','link','per_perform','total_token','token_distributed','unite_price','total_price','note','rejection_reason'];

    public function country(){
        return $this->belongsTo(Countrie::class,'country_id');
    }
    public function social(){
        return $this->belongsTo(SocialMedia::class,'sm_id');
    }
    public function engagement(){
        return $this->belongsTo(SocialMediaService::class,'sms_id');
    }
    public function creator(){
        return $this->belongsTo(User::class,'user_id');
    }
    public function reviewer(){
        return $this->belongsTo(User::class,'verified_by');  
    }
    public function reviewerCountry(){
        return $this->hasOneThrough(Countrie::class, User::class,'id','id','verified_by','country_id')->select('countries.id', 'countries.name', 'countries.flag');
    }

}

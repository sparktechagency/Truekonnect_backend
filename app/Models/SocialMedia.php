<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialMedia extends Model
{
    protected $fillable=['name','icon_url'];

    public function socialAccount(){
        return $this->hasMany(SocialAccount::class,'id');
    }
}

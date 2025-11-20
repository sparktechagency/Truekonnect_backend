<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Countrie extends Model
{
    protected $fillable=['name','dial_code','flag','token_rate','currency_code'];

    public function task(){
        return $this->belongsToMany(Task::class);
    }
}

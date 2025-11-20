<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialMediaService extends Model
{
    protected $fillable = [
        'sm_id',
        'country_id',
        'engagement_name',
        'description',
        'min_quantity',
        'unit_price',
    ];

     public function socialMedia()
    {
        return $this->belongsTo(SocialMedia::class, 'sm_id');
    }

    public function country()
    {
        return $this->belongsTo(Countrie::class, 'country_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class SupportTicket extends Model
{
    use HasFactory;
    protected $fillable = [
    'user_id',
    'subject',
    'issue',
    'attachments',
    'status',
    'admin_reason'
];

    public function ticketcreator(){
        return $this->belongsTo(User::class,'user_id');
    }
    public function reviewer(){
        return $this->belongsTo(User::class,'reviewed_by');
    }

    public function country(): HasOneThrough
    {
        return $this->hasOneThrough(Countrie::class,User::class,'id','id','user_id','country_id');
    }
}

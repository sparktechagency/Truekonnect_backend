<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
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
}

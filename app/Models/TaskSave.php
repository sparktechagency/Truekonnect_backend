<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskSave extends Model
{
    protected $fillable=['user_id','task_id'];
    public function task(){
        return $this->belongsTo(Task::class,'task_id');
    }
}

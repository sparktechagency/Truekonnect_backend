<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskPerformer extends Model
{
    use HasFactory;
        protected $fillable=['user_id','task_id','token_earned','status','verified_by','rejection_reason'];

        public function task(){
                return $this->belongsTo(Task::class,'task_id','id');
        }
        public function country(){
                return $this->hasOneThrough(Countrie::class,Task::class,'id','id', 'task_id','country_id');
        }
        public function engagement(){
                return $this->hasOneThrough(SocialMediaService::class,Task::class,'id','id', 'task_id','sms_id');
        }
        public function creator(){
                return $this->hasOneThrough(User::class,Task::class,'id','id', 'task_id','user_id');
        }
        public function performer(){
                return $this->belongsTo(User::class,'user_id','id');
        }
        public function reviewer()
        {
            return $this->belongsTo(User::class,'verified_by');
        }
        public function taskAttached(){
                return $this->hasMany(TaskFile::class,'tp_id','id');
        }

         public function taskPerformerSocialAc(){
             return $this->hasManyThrough(
                 SocialAccount::class,
                 User::class,
                 'id',
                 'user_id',
                 'user_id',
                 'id'
             );
         }

         public function getStatusAttribute($value)
         {
             return ucfirst($value);
         }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskPerformer extends Model
{
        protected $fillable=['user_id','task_id','token_earned','status','verified_by','rejection_reason'];

        public function  task(){
                return $this->belongsTo(Task::class,'task_id');
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
                return $this->belongsTo(User::class,'user_id');
        }
        public function reviewer()
        {
            return $this->belongsTo(User::class,'verified_by');
        }
        public function taskAttached(){
                return $this->hasMany(TaskFile::class,'tp_id');
        }

        // public function taskPerformerSocialAc(){
        //         return $this->hasOneThrough(User::class,Task::class,SocialAccount::class,'id','id','id', 'task_id','user_id',);
        // }

}

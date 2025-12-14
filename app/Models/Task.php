<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Task extends Model
{
    use HasFactory;
    protected $fillable=['sm_id','sms_id','user_id','country_id','quantity','description','link','per_perform','total_token','token_distributed','unite_price','total_price','note','rejection_reason'];

    public function country(){
        return $this->belongsTo(Countrie::class,'country_id');
    }
    public function social(){
        return $this->belongsTo(SocialMedia::class,'sm_id','id');
    }
    public function engagement(){
        return $this->belongsTo(SocialMediaService::class,'sms_id');
    }
    public function creator(){
        return $this->belongsTo(User::class,'user_id','id');
    }
    public function reviewer(){
        return $this->belongsTo(User::class,'verified_by');
    }
    public function reviewerCountry(){
        return $this->hasOneThrough(Countrie::class, User::class,'id','id','verified_by','country_id')->select('countries.id', 'countries.name', 'countries.flag');
    }

    public function performers(){
        return $this->hasOne(TaskPerformer::class, 'task_id', 'id');
    }

    public function users(){
        return $this->hasOneThrough(
            User::class,
            TaskPerformer::class,
            'task_id',
            'id',
            'id',
            'user_id');
    }
    public function taskFiles(){
        return $this->hasManyThrough(
            TaskFile::class,
            TaskPerformer::class,
            'task_id',
            'tp_id',
            'id',
            'id'
        );
    }

    public function socialAccount(): HasOneThrough
    {
        return $this->hasOneThrough(SocialAccount::class,TaskPerformer::class,'task_id','user_id','id','user_id');
    }

    public function tasksave()
    {
        return $this->hasMany(TaskSave::class, 'task_id');
    }

    protected $appends = ['is_saved_by_user'];

    public function getIsSavedByUserAttribute()
    {
        $userId = auth()->id();
        return $this->tasksave()->where('user_id', $userId)->exists();
//        return $this->performers()
//            ->where('user_id', $userId)
//            ->exists();
    }

}

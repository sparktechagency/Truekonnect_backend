<?php

namespace App\Http\Controllers\API;

use App\Models\Task;
use App\Models\User;
use App\Models\Countrie;
use App\Models\TaskFile;
use App\Models\TaskSave;
use App\Notifications\UserNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\TaskPerformer;
use App\Models\SocialMediaService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    //App For Brand
    public function createTask(Request $request){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'sm_id'       => 'required|integer|exists:social_media,id',
                'country_id'  => 'required|integer|exists:countries,id',
                'sms_id'      => 'required|integer|exists:social_media_services,id',
                'quantity'    => 'required|integer|min:'.$request->min_quantity,
                'description' => 'required|string',
                'link'        => 'required|url',
                'min_quantity'=> 'required|numeric|min:1',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $sms=SocialMediaService::findOrFail($request->sms_id)->first();
            $country=Countrie::findOrFail($request->country_id)->first();

            $totalprice=$sms->unit_price*$request->quantity;
            $performerAmount=$totalprice/2;
            $perperfrom=$performerAmount/ $request->quantity / $country->token_rate;
            $totaltoken=$performerAmount/$country->token_rate;
            $user=JWTAuth::user();

            $allUser = User::where('id','!=',$user->id)->get();

            $task = Task::create([
                'sm_id'             => $request->sm_id,
                'sms_id'            => $request->sms_id,
                'user_id'           => $user->id,
                'country_id'        => $request->country_id,
                'quantity'          => $request->quantity,
                'description'       => $request->description,
                'link'              => $request->link,
                'per_perform'       => $perperfrom,
                'total_token'       => $totaltoken,
                'unite_price'       => $sms->unit_price,
                'total_price'       => $totalprice,
            ]);

            $title = 'New Task Created!';
            $body = 'Task: '. $task->description . '. Quantity: ' .$task->quantity. '. Reward per perform: ' .$task->per_perform;

            foreach ($allUser as $users) {
                $users->notify(new UserNotification($title, $body));
            }
            DB::commit();
            return $this->successResponse($task, 'Task created successfully', Response::HTTP_CREATED);

        } catch (QueryException $e) {
            DB::rollback();
            return $this->errorResponse('Database error while creating task.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function myTask(){
        try {
            $tasks = Task::with([
                'country:id,name,flag,currency_code',
                'social:id,name,icon_url',
                'engagement:id,engagement_name',
                'creator:id,name,avatar'
            ])->whereIn('status', ['pending','verifyed','rejected','completed','admin_review'])->orderBy('created_at', 'desc')->where('user_id',Auth::id())->paginate(10,[
                'id',
                'sm_id','country_id','sms_id','user_id',
                'quantity',
                'description',
                'link',
                'performed',
                'per_perform',
                'token_distributed',
                'total_price',
                'status',
                'created_at'
            ]);

            return $this->successResponse($tasks, 'All tasks fetched successfully', Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function myTaskDetails(Request $request,$id)
    {
        try {
            $task = Task::with(['country:id,name,flag','social:id,name'])->where('id',$id)->where('user_id',Auth::id())->first();
            return $this->successResponse($task, 'Task fetched successfully', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function editTask(Request $request, $id){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'description' => 'nullable|string',
                'link' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                $this->errorResponse('Validation failed.'. $validator->errors(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $task = Task::findOrFail($id);

            $updated = false;

            if ($request->filled('description')) {
                $task->description = $request->description;
                $updated = true;
            }

            if ($request->filled('link')) {
                $task->link = $request->link;
                $updated = true;
            }

            if ($updated) {
                $task->status = 'pending';
                $task->save();
            }

            DB::commit();
            return $this->successResponse($task, $updated ? 'Task information updated successfully.': 'No changes were made.', Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found.'.$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while updating the task.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //Reviewer Panel
    public function allTask(Request $request){
        $perPage = $request->query('per_page', 10);
        try {
            $tasks = Task::with([
                'country:id,name,flag',
                'social:id,name,icon_url',
                'engagement:id,engagement_name',
                'creator:id,name,avatar'
            ])->where('status','pending')->orderBy('created_at', 'desc')->paginate($perPage,[
                'id',
                'sm_id','country_id','sms_id','user_id',
                'quantity',
                'description',
                'link',
                'per_perform',
                'status',
                'created_at'
            ]);

            return $this->successResponse($tasks, 'All tasks fetched successfully', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function approveTask($id){
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $task = Task::findOrFail($id);


            if ($task->status === 'verifyed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task has already been verifyed.',
                ], 409);
            }

            if ($task->status === 'rejected' || $task->status === 'pending') {
                $task->status = 'verifyed';
                $task->verified_by = $user->id;
                $task->save();

                $title = 'Your task has been approved.';
                $body = 'Your task, '.$task->engagement->engagement_name. ', has been approved.';

                $task->creator->notify(new UserNotification($title, $body));

                DB::commit();

                return $this->successResponse($task, 'Task approved successfully.', Response::HTTP_OK);
            }

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found.'.$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token has expired. Please log in again.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while approving the task.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function rejectTask(Request $request, $id){
        DB::beginTransaction();
        try {

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed.'. $validator->errors(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = JWTAuth::parseToken()->authenticate();

            $task = Task::findOrFail($id);

            if ($task->status === 'rejected') {
                return $this->errorResponse('This task has already been rejected.',Response::HTTP_CONFLICT);
            }

            if ($task->status === 'verifyed') {
                return $this->errorResponse('This task has already been verified.',Response::HTTP_CONFLICT);
            }

            if (in_array($task->status, ['pending', 'admin_review'])) {
                $task->status = 'rejected';
                $task->rejection_reason = $request->rejection_reason;
                $task->verified_by = $user->id;
                $task->updated_at = now();
                $task->save();

                $title = 'Your task has been rejected.';
                $body = 'Your task, '.$task->engagement->engagement_name. ', has been rejected.';

                $task->creator->notify(new UserNotification($title, $body));

                return $this->successResponse($task, 'Task rejected successfully.', Response::HTTP_OK);
            }

            DB::commit();

            return $this->errorResponse('Task cannot be rejected in its current state.', Response::HTTP_BAD_REQUEST);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found.', Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while rejecting the task.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminReview(Request $request, $id){
        DB::beginTransaction();
        try {

            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed.'. $validator->errors(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            $user = JWTAuth::parseToken()->authenticate();


            $task = Task::findOrFail($id);


            if ($task->status === 'admin_review'||$task->status === 'verifyed'||$task->status === 'completed') {
                return $this->errorResponse('This task has already been '.$task->status,Response::HTTP_CONFLICT);
            }


            if (in_array($task->status, ['pending', 'rejected'])) {
                $task->status = 'admin_review';
                $task->note = $request->note;
                $task->verified_by = $user->id;
                $task->updated_at = now();
                $task->save();

                $title = 'Your task is in admin review.';
                $body = 'Your task, '.$task->engagement->engagement_name. ', is in admin review.';

                $task->creator->notify(new UserNotification($title, $body));

                DB::commit();
                return $this->successResponse($task, 'Task assign for admin review successfully.', Response::HTTP_OK);
            }

            DB::rollback();
            return $this->errorResponse('Task status cannot be processed', Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found.' .$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token has expired. Please log in again.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while admin review the task.'.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function allPerformTask(Request $request){
        $perPage = $request->query('per_page', 10);
        try {
            $tasks = TaskPerformer::with([
                'task',
                'taskAttached:id,tp_id,file_url',
                'country',
                'engagement',
                'creator'=>function($q){
                    $q->select('users.id as user_id', 'users.name', 'users.avatar');
                },
                'performer',
                ])->where('status','pending')->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->successResponse($tasks, 'All tasks fetched successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function ptapproved($id){
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $task = TaskPerformer::findOrFail($id);
            $performer=User::find($task->user_id);
            if ($task->status === 'completed') {
                return $this->errorResponse('This task has already been '.$task->status,Response::HTTP_CONFLICT);
            }
            if (!$performer) {
                return $this->errorResponse('This task does not exists.', Response::HTTP_NOT_FOUND);
            }

            if ($task->status === 'pending') {
                $task->status = 'completed';
                $task->verified_by = $user->id;
                $task->save();
                $performer->earn_token+=$task->token_earned;
                $performer->save();

                $title = 'Your task is completed.';
                $body = $performer->name. ', your task is completed. You earned '.$task->token_earned.' tokens.';

                $performer->notify(new UserNotification($title, $body));
                DB::commit();
                return $this->successResponse($performer, 'Task approved successfully.', Response::HTTP_OK);
            }
            DB::rollback();
            return $this->errorResponse('Task status cannot be processed', Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ' .$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong'.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function ptrejectTask(Request $request,$id){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed.'. $validator->errors(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user = JWTAuth::parseToken()->authenticate();

            $task = TaskPerformer::findOrFail($id);

            if ($task->status === 'completed') {
                return $this->errorResponse('This task has already been '.$task->status,Response::HTTP_CONFLICT);
            }
            if ($task->status === 'rejected') {
                return $this->errorResponse('This task has already been '.$task->status,Response::HTTP_CONFLICT);
            }

            if ($task->status === 'pending') {
                $task->status = 'rejected';
                $task->verified_by = $user->id;
                $task->rejection_reason = $request->rejection_reason;
                $task->save();

                $performer = User::find($task->user_id);

                if ($performer) {
                    $title = 'Your task is rejected';
                    $body = "Hello {$performer->name}, your task has been rejected. Reason: {$task->rejection_reason}";

                    $performer->notify(new UserNotification($title, $body));
                }
                DB::commit();
                return $this->successResponse($performer, 'Task rejected successfully.', Response::HTTP_OK);
            }
            DB::rollback();
            return $this->errorResponse('Task status cannot be processed', Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ' .$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong'.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function ptadminReview(Request $request,$id){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed.'. $validator->errors(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user = JWTAuth::parseToken()->authenticate();

            $task = TaskPerformer::findOrFail($id);

            if ($task->status === 'admin_review') {
                return $this->errorResponse('This task has already been '.$task->status,Response::HTTP_CONFLICT);
            }
            if ($task->status === 'rejected') {
                return $this->errorResponse('This task has already been '.$task->status,Response::HTTP_CONFLICT);
            }

            if ($task->status === 'pending') {
                $task->status = 'admin_review';
                $task->verified_by = $user->id;
                $task->rejection_reason = $request->rejection_reason;
                $task->save();

                $performer = User::find($task->user_id);
                if ($performer) {
                    $title = 'Your task is under admin review';
                    $body = "Hello {$performer->name}, your task has been assigned for admin review. Reason: {$task->rejection_reason}";

                    $performer->notify(new UserNotification($title, $body));
                }
                DB::commit();
                return $this->successResponse($performer, 'Task moved to admin', Response::HTTP_OK);
            }
            DB::rollback();
            return $this->errorResponse('Task status cannot be processed', Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ' .$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong'.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    // App For Performer
    public function availableTasksForMe()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
            }

            if (!$user->country_id) {
                return $this->errorResponse('User country not found', Response::HTTP_NOT_FOUND);
            }

            $perPage =10;
            $tasks = Task::with([
                    'country:id,name,flag',
                    'social:id,name,icon_url',
                    'engagement:id,engagement_name',
                    'creator:id,name,avatar'
                ])
                ->where('status', 'verifyed')
                ->whereColumn('quantity', '!=', 'performed')
                ->where('country_id', $user->country_id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage,[
                    'id',
                    'sm_id',
                    'country_id',
                    'sms_id',
                    'user_id',
                    'quantity',
                    'description',
                    'link',
                    'per_perform',
                    'status',
                    'performed',
                    'created_at'
                ]);

            if ($tasks->isEmpty()) {
                return $this->successResponse(null, 'No available task for your country yet', Response::HTTP_OK);
            }

            return $this->successResponse([
                'username'=>$user->name,
                'tasks'=>$tasks
            ], 'All tasks fetched successfully.', Response::HTTP_OK);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->errorResponse('Token expired. Please log in again.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong'.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function saveTask(Request $request)
    {
        DB::beginTransaction();
        try {

            $validator = Validator::make($request->all(), [
                'task_id' => 'required|integer|exists:tasks,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'error_type' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }


            $user = JWTAuth::parseToken()->authenticate();

            $alreadySaved = TaskSave::where('user_id', $user->id)
                ->where('task_id', $request->task_id)
                ->exists();

            if ($alreadySaved) {
                return $this->errorResponse('This task already exists.', Response::HTTP_CONFLICT);
            }

            $saveTask = TaskSave::create([
                'user_id' => $user->id,
                'task_id' => $request->task_id,
            ]);

            DB::commit();

            return $this->successResponse($saveTask, 'Task saved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function submitTask(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'task_id'        => 'required|integer|exists:tasks,id',
                'task_attached'  => 'required|array|min:1|max:5',
                'task_attached.*'=> 'file|mimetypes:image/jpeg,image/jpg,image/png,image/webp|max:20480',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed.'. $validator->errors(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $task = Task::findOrFail($request->task_id);

            if ($task->quantity == $task->performed) {
                return $this->errorResponse("Task was completed. You can't perform it", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $alreadySubmitted = TaskPerformer::where('user_id', $user->id)
                ->where('task_id', $request->task_id)
                ->exists();

            if ($alreadySubmitted) {
                return $this->errorResponse('This task already submitted.', Response::HTTP_CONFLICT);
            }

            DB::beginTransaction();

            $performer = TaskPerformer::create([
                'user_id'      => $user->id,
                'task_id'      => $request->task_id,
                'token_earned' => $task->per_perform,
                'status'       => 'pending',
            ]);

            $taskUpdate = Task::where('id',$request->task_id)->increment('performed',1);
            $taskDistribution = Task::where('id',$request->task_id)->increment('token_distributed', $task->per_perform);


            $uploadedFiles = [];
            foreach ($request->file('task_attached') as $file) {
                $path = $file->store('task_files', 'public');
                $uploadedFiles[] = $path;

                TaskFile::create([
                    'tp_id'     => $performer->id,
                    'file_url'  => $path,
                ]);
            }

            $task->refresh();

            if ($task->quantity == $task->performed) {
                $task->status = 'completed';
                $task->save();
            }

//            if (count($uploadedFiles) === 0) {
//                $performer->delete();
//                return $this->errorResponse('At least one image must be uploaded.', Response::HTTP_UNPROCESSABLE_ENTITY);
//            }
            $creator = $task->creator;
            if ($creator) {
                $title = 'New Task Submission';
                $body  = "{$user->name} has submitted the task '{$task->engagement->engagement_name}' for your review.";

                $creator->notify(new UserNotification($title, $body));
            }
            DB::commit();
            return $this->successResponse([
                'performer'=>$performer,
                'files'=>$uploadedFiles
            ], 'Task submitted successfully.', Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function myPerformTask(Request $request){
         $perPage = $request->query('per_page', 10);

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $tasks = TaskPerformer::with([
                'task',
                'taskAttached:id,tp_id,file_url',
                'country',
                'engagement',
                'creator'=>function($q){
                    $q->select('users.id as user_id', 'users.name', 'users.avatar');
                },
                'performer:id,name,avatar',
                ])->where('user_id',$user->id)->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->successResponse($tasks, 'Tasks retrieved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //admin

    public function adminSupportTask(Request $request){
         $perPage = $request->query('per_page', 10);
        try {
            $tasks = Task::with([
                'country:id,name,flag',
                'social:id,name,icon_url',
                'engagement:id,engagement_name',
                'reviewer:id,name,email,phone,avatar',
                'reviewerCountry'
            ])->where('status','admin_review')->orderBy('created_at', 'desc')->paginate($perPage,[
                'id',
                'sm_id','country_id','sms_id','user_id',
                'quantity',
                'description',
                'link',
                'per_perform',
                'status',
                'verified_by',
                'rejection_reason',
                'note',
                'created_at'
            ]);
            return $this->successResponse($tasks, 'Tasks retrieved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminApproveTask($id){
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $task = Task::findOrFail($id);

            if ($task->status === 'verifyed') {
                return $this->errorResponse('This task is already approved.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($task->status === 'admin_review') {
                $task->status = 'verifyed';
                $task->verified_by = $user->id;
                $task->save();

                $title = 'Task Approved';
                $body = 'Hello '.$task->creator->name.'!. Your task, ' .$task->engagement->engagement_name.' has been approved.';

                $task->creator->notify(new UserNotification($title, $body));

                DB::commit();

                return $this->successResponse($task, 'Task approved successfully.', Response::HTTP_OK);
            }
            DB::rollback();
            return $this->errorResponse('This task is already approved.', Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found.', Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired.', Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Token Invalid.', Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminRejectedTask(Request $request, $id){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();

            $task = Task::findOrFail($id);

            if ($task->status === 'rejected') {
                return $this->errorResponse('This task is already rejected.', Response::HTTP_FORBIDDEN);
            }


            // Handle rejection
            if ($task->status=='admin_review') {
                $task->status = 'rejected';
                $task->rejection_reason = $request->rejection_reason;
                $task->verified_by = $user->id;
                $task->updated_at = now();
                $task->save();

                $title = 'Task Rejected';
                $body = 'Hello '.$task->creator->name.'!. Your task, ' .$task->engagement->engagement_name.' has been rejected. Reason: '.$task->rejection_reason;

                $task->creator->notify(new UserNotification($title, $body));

                DB::commit();

                return $this->successResponse($task, 'Task rejected successfully.', Response::HTTP_OK);
            }

            DB::rollback();
            return $this->errorResponse('This task is already rejected.', Response::HTTP_FORBIDDEN);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. '.$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. '.$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Token Invalid. '.$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminSupportPerformTask(Request $request){
         $perPage = $request->query('per_page', 10);
        try {
            $tasks = TaskPerformer::with([
                'task',
                'taskAttached:id,tp_id,file_url',
                'country',
                'engagement',
                'creator'=>function($q){
                    $q->select('users.id as user_id', 'users.name', 'users.avatar');
                },
                'performer:id,name,avatar',
                ])->where('status','admin_review')->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->successResponse($tasks, 'Tasks retrieved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminApprovedSPerformTask($id){
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $task = TaskPerformer::findOrFail($id);
            $performer=User::find($task->user_id);
            if ($task->status === 'completed') {
                return $this->errorResponse('This task is already approved.', Response::HTTP_FORBIDDEN);
            }
            if (!$performer) {
                return $this->errorResponse('Performer not found.', Response::HTTP_NOT_FOUND);
            }

            if ($task->status === 'admin_review') {
                $task->status = 'completed';
                $task->verified_by = $user->id;
                $task->save();
                $performer->earn_token+=$task->token_earned;
                $performer->save();

                $title = 'Task Approved';
                $body = 'Congratulations! Your performed task, '.$task->engagement->engagement_name.' has been approved. You earned '.$task->token_earned.' token.';

                $performer->notify(new UserNotification($title, $body));

                DB::commit();
                return $this->successResponse($performer, 'Task approved successfully.', Response::HTTP_OK);
            }
            DB::rollback();
            return $this->errorResponse('This task is already approved.', Response::HTTP_FORBIDDEN);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. '.$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. '.$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Token Invalid. '.$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminRejectedSPerformTask(Request $request, $id){
        DB::beginTransaction();
         try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed. '.$validator->errors(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $user = JWTAuth::parseToken()->authenticate();

            $task = TaskPerformer::findOrFail($id);


            if ($task->status === 'completed') {
                return $this->errorResponse('This task is already approved.', Response::HTTP_CONFLICT);
            }
            if ($task->status === 'rejected') {
                return $this->errorResponse('This task is already rejected.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if ($task->status === 'admin_review') {
                $task->status = 'rejected';
                $task->verified_by = $user->id;
                $task->rejection_reason = $request->rejection_reason;
                $task->save();

                $performer=User::find($task->user_id);
                $title = 'Task Rejected';
                $body = 'Sorry! Your performed task, '.$task->engagement->engagement_name.' has been rejected.';

                $performer->notify(new UserNotification($title, $body));

                DB::commit();
                return $this->successResponse($task, 'Task rejected successfully.', Response::HTTP_OK);
            }

            DB::rollback();
            return $this->errorResponse('This task is already rejected.', Response::HTTP_FORBIDDEN);

        } catch (ModelNotFoundException $e) {
             DB::rollback();
             return $this->errorResponse('Task not found. '.$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
             DB::rollback();
             return $this->errorResponse('Token expired. '.$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
             DB::rollback();
             return $this->errorResponse('Token Invalid. '.$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
             DB::rollback();
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /* Rubyaet */
    public function taskManagement()
    {
        try {
            $activeTask = Task::with(['engagement:id,engagement_name','creator:id,name'])
                ->where('status', 'verifyed')
                ->whereColumn('quantity', '>', 'performed')
                ->paginate(10,['id','sms_id','quantity']);
            $completeTask = Task::with(['engagement:id,engagement_name','creator:id,name'])
                ->where('status', 'verifyed')
                ->whereColumn('quantity', '=', 'performed')->paginate(10,['id','sms_id','quantity']);
            $rejectedTask = Task::with(['engagement:id,engagement_name','creator:id,name'])->where('status', 'rejected')->paginate(10,['id','sms_id','quantity']);

            return $this->successResponse([
                'Active Task' => $activeTask,
                'Completed Task' => $completeTask,
                'Rejected Task' => $rejectedTask
            ], 'All Active Task', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ' .$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function taskDetails($taskId)
    {
        try {
            $check = Task::findOrFail($taskId);
            if (!$check){
                return $this->errorResponse('Task not found.', Response::HTTP_NOT_FOUND);
            }
            $task = Task::with(['reviewer:id,name,email,phone,country_id','reviewer.country:id,name','country:id,name,flag'])->where('tasks.id', $taskId)
                ->first();

            $isCompleted = ($task->quantity == $task->performed);
            $status = $isCompleted ? 'Complete Task' : 'Active Task';

            $response = [
                $status => $task,
            ];

            if ($isCompleted) {
                $response['Total Performed Task'] = $task->performed;
                $response['Token Distribution'] = $task->token_distribution ?? 0;
            }

            if ($task->status === 'rejected') {
                $response['Rejected Task'] = $task->rejection_reason;
            }

            return $this->successResponse($response, 'Task details', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function orderManagement()
    {
        try {
            $completedOrder = TaskPerformer::with(['performer:id,name','task:id,sms_id','task.engagement:id,engagement_name'])->where('status', 'completed')->paginate(10);

            $rejectedOrder = TaskPerformer::with(['performer:id,name','task:id,sms_id','task.engagement:id,engagement_name'])->where('status', 'rejected')->paginate(10);

            return $this->successResponse([
                'Completed Order' => $completedOrder,
                'Rejected Order' => $rejectedOrder
            ], 'All Orders', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function orderDetails($orderId)
    {
        try {
            $order = TaskPerformer::with([
                'task:id,sm_id,sms_id,total_token,created_at,link',
                'task.social:id,name',
                'taskAttached:id,tp_id,file_url',
                'reviewer:id,name,email,phone,country_id',
                'reviewer.country:id,name,flag'
            ])->where('task_performers.id', $orderId)
                ->paginate(10);

            $order = $order->map(function ($item) {
                if ($item->task_status === 'rejected') {
                    $item->rejected_reason = $item->rejection_reason;
                }
                return $item;
            });

            return $this->successResponse($order, 'All Orders', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

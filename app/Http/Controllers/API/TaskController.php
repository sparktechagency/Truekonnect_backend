<?php

namespace App\Http\Controllers\API;

use App\Models\Task;
use App\Models\User;
use App\Models\Countrie;
use App\Models\TaskFile;
use App\Models\TaskSave;
use Illuminate\Http\Request;
use App\Models\TaskPerformer;
use App\Models\SocialMediaService;
use Illuminate\Http\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    //App For Brand
    public function createTask(Request $request){
            try {
            $validator = Validator::make($request->all(), [
                'sm_id'       => 'required|integer|exists:social_media,id',
                'country_id'  => 'required|integer|exists:countries,id',
                'sms_id'      => 'required|integer|exists:social_media_services,id',
                'quantity'    => 'required|integer|min:'.$request->min_quantity,
                'description' => 'required|string',
                'link'        => 'required|url',
                'price'       => 'required|numeric',
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
            // Create the task
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

            return response()->json([
                'status'  => true,
                'message' => 'Task created successfully.',
                'data'    => $task,
            ], 201);

        } catch (QueryException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Database error while creating task.',
                'error'   => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function myTask(){
        try {
            $tasks = Task::with([
                'country:id,name,flag,currency_code',
                'social:id,name,icon_url',
                'engagement:id,engagement_name',
                'creator:id,name,avatar'
            ])->where('status','pending')->orderBy('created_at', 'desc')->paginate(10,[
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

            return response()->json([
                'status'  => true,
                'message' => 'All tasks fetched successfully.',
                'data'    => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch tasks.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function editTask(Request $request, $id){
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'description' => 'nullable|string',
                'link' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Retrieve the task
            $task = Task::findOrFail($id);

            // Update fields conditionally
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

            return response()->json([
                'status' => true,
                'message' => $updated
                    ? 'Task information updated successfully.'
                    : 'No changes were made.',
                'data' => $task,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Exception $e) {
            // Catch-all for any other error
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while updating the task.',
                'error' => $e->getMessage(),
            ], 500);
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

            return response()->json([
                'status'  => true,
                'message' => 'All tasks fetched successfully.',
                'data'    => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch tasks.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function approveTask($id){
        try {
            $user = JWTAuth::parseToken()->authenticate();
            // Find the task
            $task = Task::findOrFail($id);

            // Make sure it’s not already processed
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
                return response()->json([
                    'status' => true,
                    'message' => 'Task verifyed successfully.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while approving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function rejectTask(Request $request, $id){

        try {
            // Validate input
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

            // Authenticate user
            $user = JWTAuth::parseToken()->authenticate();

            // Find the task
            $task = Task::findOrFail($id);

            // Prevent double-processing
            if ($task->status === 'rejected') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task has already been rejected.',
                ], 409);
            }

            if ($task->status === 'verifyed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task has already been verified.',
                ], 409);
            }

            // Handle rejection
            if (in_array($task->status, ['pending', 'admin_review'])) {
                $task->status = 'rejected';
                $task->rejection_reason = $request->rejection_reason;
                $task->verified_by = $user->id;
                $task->updated_at = now();
                $task->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Task rejected successfully.',
                    'task' => $task,
                ], 200);
            }

            // Catch any weird statuses
            return response()->json([
                'status' => false,
                'message' => 'Task cannot be rejected in its current state.',
            ], 400);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while rejecting the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function adminReview(Request $request, $id){

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // Authenticate user
            $user = JWTAuth::parseToken()->authenticate();

            // Find the task
            $task = Task::findOrFail($id);

            // Prevent double-processing
            if ($task->status === 'admin_review'||$task->status === 'verifyed'||$task->status === 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task has already been '.$task->status,
                ], 409);
            }

            // Handle rejection
            if (in_array($task->status, ['pending', 'rejected'])) {
                $task->status = 'admin_review';
                $task->note = $request->note;
                $task->verified_by = $user->id;
                $task->updated_at = now();
                $task->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Task assign for admin review successfully.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while admin review the task.',
                'error' => $e->getMessage(),
            ], 500);
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

            return response()->json([
                'status'  => true,
                'message' => 'All tasks fetched successfully.',
                'data'    => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch tasks.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function ptapproved($id){
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $task = TaskPerformer::findOrFail($id);
            $performer=User::find($task->user_id);
            if ($task->status === 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been approved.',
                ], 409);
            }
            if (!$performer) {
                return response()->json([
                    'status' => false,
                    'message' => 'This task performer not found.',
                ], 409);
            }

            if ($task->status === 'pending') {
                $task->status = 'completed';
                $task->verified_by = $user->id;
                $task->save();
                $performer->earn_token+=$task->token_earned;
                $performer->save();




                return response()->json([
                    'status' => true,
                    'message' => 'Task Perform approved successfully.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while approving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function ptrejectTask(Request $request,$id){
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
            // Find the task
            $task = TaskPerformer::findOrFail($id);

            // Make sure it’s not already processed
            if ($task->status === 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been approved.',
                ], 409);
            }
            if ($task->status === 'rejected') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been rejected.',
                ], 409);
            }

            if ($task->status === 'pending') {
                $task->status = 'rejected';
                $task->verified_by = $user->id;
                $task->rejection_reason = $request->rejection_reason;
                $task->save();
                return response()->json([
                    'status' => true,
                    'message' => 'Task Perform rejected successfully.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while approving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function ptadminReview(Request $request,$id){
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
            // Find the task
            $task = TaskPerformer::findOrFail($id);

            // Make sure it’s not already processed
            if ($task->status === 'admin_review') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been assign for admin review.',
                ], 409);
            }
            if ($task->status === 'rejected') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been rejected.',
                ], 409);
            }

            if ($task->status === 'pending') {
                $task->status = 'admin_review';
                $task->verified_by = $user->id;
                $task->rejection_reason = $request->rejection_reason;
                $task->save();
                return response()->json([
                    'status' => true,
                    'message' => 'Task Perform has assign for admin review.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while approving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // App For Performer
    public function availableTasksForMe()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->country_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'User country not set. Please update your profile.'
                ], 400);
            }

           // $perPage = $request->query('per_page', 10);
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
                ->get([
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
                return response()->json([
                    'status' => true,
                    'message' => 'No available tasks for your country yet.',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Available tasks fetched successfully.',
                'data' => $tasks
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token expired. Please log in again.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid token.'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Failed to fetch available tasks: '.$e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching available tasks.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function saveTask(Request $request)
    {
        try {
            // Validate the input
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

            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();

            // Check if user already saved this task
            $alreadySaved = TaskSave::where('user_id', $user->id)
                ->where('task_id', $request->task_id)
                ->exists();

            if ($alreadySaved) {
                return response()->json([
                    'status' => false,
                    'message' => 'You already saved this task. Thank You!',
                ], 409);
            }

            // Save the task
            $saveTask = TaskSave::create([
                'user_id' => $user->id,
                'task_id' => $request->task_id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Task saved successfully.',
                'data' => $saveTask,
            ], 201);

        } catch (\Exception $e) {
            // Because everything humans touch somehow explodes eventually
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while saving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function submitTask(Request $request)
    {
        try {
            // Validate request input
            $validator = Validator::make($request->all(), [
                'task_id'        => 'required|integer|exists:tasks,id',
                'task_attached'  => 'required',
                'task_attached.*'=> 'file|mimetypes:image/jpeg,image/jpg,image/png,image/webp|max:20480',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'error_type' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $task = Task::findOrFail($request->task_id);

            // Check if user already submitted this task
            $alreadySubmitted = TaskPerformer::where('user_id', $user->id)
                ->where('task_id', $request->task_id)
                ->exists();

            if ($alreadySubmitted) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already submitted this task.',
                ], 409); // HTTP 409 Conflict
            }

            $taskUpdate = Task::where('id',$validator['task_id'])->increment('performed',1);

            // Create the performer record
            $performer = TaskPerformer::create([
                'user_id'      => $user->id,
                'task_id'      => $request->task_id,
                'token_earned' => $task->per_perform,
                'status'       => 'pending',
            ]);

            // Handle image uploads
            $uploadedFiles = [];
            foreach ($request->file('task_attached') as $file) {
                $path = $file->store('task_files', 'public');
                $uploadedFiles[] = $path;

                TaskFile::create([
                    'tp_id'     => $performer->id,
                    'file_url'  => $path,
                ]);
            }

            // If somehow no files uploaded (just in case)
            if (count($uploadedFiles) === 0) {
                $performer->delete();
                return response()->json([
                    'status' => false,
                    'message' => 'At least one image must be attached.',
                ], 400);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Task submitted successfully.',
                'data'    => [
                    'performer' => $performer,
                    'files'     => $uploadedFiles,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while submitting the task.',
                'error'   => $e->getMessage(),
            ], 500);
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

            return response()->json([
                'status'  => true,
                'message' => 'All tasks fetched successfully.',
                'data'    => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch tasks.',
                'error'   => $e->getMessage()
            ], 500);
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

            return response()->json([
                'status'  => true,
                'message' => 'All tasks fetched successfully.',
                'data'    => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch tasks.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function adminApproveTask($id){
        try {
            $user = JWTAuth::parseToken()->authenticate();
            // Find the task
            $task = Task::findOrFail($id);

            // Make sure it’s not already processed
            if ($task->status === 'verifyed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task has already been approved.',
                ], 409);
            }

            if ($task->status === 'admin_review') {
                $task->status = 'verifyed';
                $task->verified_by = $user->id;
                $task->save();
                return response()->json([
                    'status' => true,
                    'message' => 'Task verifyed successfully.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while approving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function adminRejectedTask(Request $request, $id){
        try {
            // Validate input
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

            // Authenticate user
            $user = JWTAuth::parseToken()->authenticate();

            // Find the task
            $task = Task::findOrFail($id);

            // Prevent double-processing
            if ($task->status === 'rejected') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task has already been rejected.',
                ], 409);
            }


            // Handle rejection
            if ($task->status=='admin_review') {
                $task->status = 'rejected';
                $task->rejection_reason = $request->rejection_reason;
                $task->verified_by = $user->id;
                $task->updated_at = now();
                $task->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Task rejected successfully.',
                    'task' => $task,
                ], 200);
            }

            // Catch any weird statuses
            return response()->json([
                'status' => false,
                'message' => 'Task cannot be rejected in its current state.',
            ], 400);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while rejecting the task.',
                'error' => $e->getMessage(),
            ], 500);
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

            return response()->json([
                'status'  => true,
                'message' => 'All tasks fetched successfully.',
                'data'    => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch tasks.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function adminApprovedSPerformTask($id){
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $task = TaskPerformer::findOrFail($id);
            $performer=User::find($task->user_id);
            if ($task->status === 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been approved.',
                ], 409);
            }
            if (!$performer) {
                return response()->json([
                    'status' => false,
                    'message' => 'This task performer not found.',
                ], 409);
            }

            if ($task->status === 'admin_review') {
                $task->status = 'completed';
                $task->verified_by = $user->id;
                $task->save();
                $performer->earn_token+=$task->token_earned;
                $performer->save();
                return response()->json([
                    'status' => true,
                    'message' => 'Task Perform approved successfully.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while approving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function adminRejectedSPerformTask(Request $request, $id){
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
            // Find the task
            $task = TaskPerformer::findOrFail($id);

            // Make sure it’s not already processed
            if ($task->status === 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been approved.',
                ], 409);
            }
            if ($task->status === 'rejected') {
                return response()->json([
                    'status' => false,
                    'message' => 'This task perform has already been rejected.',
                ], 409);
            }

            if ($task->status === 'admin_review') {
                $task->status = 'rejected';
                $task->verified_by = $user->id;
                $task->rejection_reason = $request->rejection_reason;
                $task->save();
                return response()->json([
                    'status' => true,
                    'message' => 'Task Perform rejected successfully.',
                    'task' => $task,
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Task not found.',
            ], 404);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while approving the task.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* Rubyaet */
    public function taskManagement()
    {
        try {
            $activeTask = Task::where('status', 'verifyed')->whereColumn('quantity', '>', 'performed')->get();
            $completeTask = Task::where('status', 'verifyed')->whereColumn('quantity', '=', 'performed')->get();
            $rejectedTask = Task::where('status', 'rejected')->get();

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
            $task = Task::where('tasks.id', $taskId)
                ->join('users', 'users.id', '=', 'tasks.user_id')
                ->select(
                    'tasks.*',
                    'users.id as user_id',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.avatar as user_avatar'
                )
                ->first();

            $isCompleted = ($task->quantity == $task->performed);
            $status = $isCompleted ? 'Complete Task' : 'Active Task';

            $response = [
                $status => $task,
            ];

            // Include extra fields if task is completed
            if ($isCompleted) {
                $response['Total Performed Task'] = $task->performed;
                $response['Token Distribution'] = $task->token_distribution ?? 0; // use 0 if null
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
            $completedOrder = TaskPerformer::where('status', 'completed')->get();

            $rejectedOrder = TaskPerformer::where('status', 'rejected')->get();

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
            $order = TaskPerformer::where('task_performers.id', $orderId)
                ->join('users', 'users.id', '=', 'task_performers.verified_by')
                ->join('task_files', 'task_files.tp_id', '=', 'task_performers.id')
                ->join('tasks', 'tasks.id', '=', 'task_performers.task_id')
                ->select('users.*', 'tasks.*', 'task_files.*', 'task_performers.status as task_status', 'task_performers.rejection_reason')
                ->get();

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

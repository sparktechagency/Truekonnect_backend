<?php

namespace App\Http\Controllers\API;

use App\Models\SocialAccount;
use App\Models\SocialMedia;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Models\Countrie;
use App\Models\TaskFile;
use App\Models\TaskSave;
use App\Notifications\UserNotification;
use Carbon\Carbon;
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

    public function engagementType(Request $request)
    {
        try {
            $data = $request->validate([
                'sm_id' => 'required',
            ]);

            $social = SocialMediaService::where('sm_id',$data['sm_id'])->where('country_id',Auth::user()->country_id)->latest()->get();

            return $this->successResponse($social,'All Engagement Types',200);
        }catch (\Exception $exception){
            return $this->errorResponse('Something went wrong',$exception->getMessage(),500);
        }
    }
    public function socialMedia()
    {
        try {
            $sm = SocialMedia::latest()->get();

            return $this->successResponse($sm,'All Engagement Types',200);
        }catch (\Exception $exception){
            return $this->errorResponse('Something went wrong',$exception->getMessage(),500);
        }
    }
    public function createTask(Request $request){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'sm_id'       => 'required|integer|exists:social_media,id',
//                'country_id'  => 'required|integer|exists:countries,id',
                'sms_id'      => 'required|integer|exists:social_media_services,id',
                'quantity'    => 'required|integer',
                'description' => 'required|string',
                'link'        => 'required|url',
//                'min_quantity'=> 'required|numeric|min:1',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()->first(),
                ], 422);
            }

            $sms=SocialMediaService::find($request->sms_id);
//            dd($sms);

            if ($sms->min_quantity > $request->quantity) {
                return $this->errorResponse('Please select a quantity of at least '.$sms->min_quantity.' to continue.',null,Response::HTTP_BAD_REQUEST);
            }
            $country=Countrie::findOrFail(Auth::user()->country_id)->first();

//            dd($country->token_rate);
//            dd($sms);
            $totalprice=$sms->unit_price*$request->quantity;
            $performerAmount=$totalprice/2;
            $perperfrom=($performerAmount/ $request->quantity) / $country->token_rate;
            $totaltoken=$performerAmount/$country->token_rate;

//            dd($totaltoken,$perperfrom,$totalprice,$performerAmount);
            $user=JWTAuth::user();

            $allUser = User::where('id','!=',$user->id)->get();

            $task = Task::create([
                'sm_id'             => $request->sm_id,
                'sms_id'            => $request->sms_id,
                'user_id'           => $user->id,
                'country_id'        => Auth::user()->country_id,
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
            return $this->errorResponse('Database error while creating task.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function myTask(Request $request){
        try {
            $data = $request->validate([
                'status' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
            ]);

            $startDate = !empty($data['start_date'])
                ? Carbon::parse($data['start_date'])->startOfDay()
                : Carbon::create(1970, 1, 1, 0, 0, 0);

            $endDate = !empty($data['start_date'])
                ? Carbon::parse($data['start_date'])->endOfDay()
                : Carbon::create(2100, 12, 31, 23, 59, 59);

            if ($data['status'] == 'ongoing') {
                $tasks = Task::with([
                    'country:id,name,flag,currency_code',
                    'social:id,name,icon_url',
                    'engagement:id,engagement_name',
                    'creator:id,name,avatar'
                ])
                    ->whereColumn('quantity','>','performed')
//                    ->where('status', $data['status'] ?? 'pending')
                    ->orderBy('created_at', 'desc')
                    ->where('user_id', Auth::id())
//                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->paginate(10, [
                        'id',
                        'sm_id', 'country_id', 'sms_id', 'user_id',
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
            }else {
                $tasks = Task::with([
                    'country:id,name,flag,currency_code',
                    'social:id,name,icon_url',
                    'engagement:id,engagement_name',
                    'creator:id,name,avatar'
                ])->where('status', $data['status'] ?? 'pending')->orderBy('created_at', 'desc')->where('user_id', Auth::id())
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->paginate(10, [
                        'id',
                        'sm_id', 'country_id', 'sms_id', 'user_id',
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
            }


            return $this->successResponse($tasks, 'All tasks fetched successfully', Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function myTaskDetails(Request $request,$id)
    {
        try {
            $task = Task::with(['country','social','engagement'])->where('id',$id)->where('user_id',Auth::id())->first();
            return $this->successResponse($task, 'Task fetched successfully', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function whoGotPaid()
    {
        try {
            $taskPerformer = Task::with(['country','creator','reviewer','performers','engagement','users'])->where('user_id',Auth::id())
                ->where('status','completed')
//                ->count();
                ->latest()->paginate(10);

            $totalUser = $taskPerformer->count();
            $totalToken = $taskPerformer->sum('token_distributed');

            $response = [
                'totalUser' => $totalUser,
                'totalToken' => $totalToken,
                'taskPerformer' => $taskPerformer,

            ];


            return $this->successResponse($response, 'Task fetched successfully', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something Went Wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
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
            return $this->errorResponse('Task not found.',$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while updating the task.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //Reviewer Panel
    public function allTask(Request $request){
        $perPage = $request->query('per_page', 10);
        $search  = $request->query('search');
        try {
            $tasks = Task::with([
                'country:id,name,flag',
                'social:id,name,icon_url',
                'engagement:id,engagement_name',
                'creator:id,name,email,avatar'
            ])->when($search, function ($q) use ($search) {

                $q->whereHas('engagement', function ($eng) use ($search) {
                    $eng->where('engagement_name', 'like', "%{$search}%");
                })

                    ->orWhereHas('creator', function ($creator) use ($search) {
                        $creator->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });

            })

                ->where('status','pending')->orderBy('created_at', 'desc')->paginate($perPage,[
                'id',
                'sm_id','country_id','sms_id','user_id',
                'quantity',
                'description',
                'link',
                'total_token',
                'per_perform',
                'status',
                'created_at'
            ]);

            return $this->successResponse($tasks, 'All tasks fetched successfully', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
            return $this->errorResponse('Task not found.',$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token has expired. Please log in again.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while approving the task.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function rejectTask(Request $request, $id){
//        DB::beginTransaction();
        try {

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = JWTAuth::parseToken()->authenticate();

            $task = Task::findOrFail($id);

            if ($task->status == 'rejected') {
                return $this->errorResponse('This task has already been rejected.',null,Response::HTTP_CONFLICT);
            }

            if ($task->status == 'verifyed') {
                return $this->errorResponse('This task has already been verified.',null,Response::HTTP_CONFLICT);
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

//            DB::commit();

            return $this->errorResponse('Task cannot be rejected in its current state.',null, Response::HTTP_BAD_REQUEST);

        } catch (ModelNotFoundException $e) {
//            DB::rollback();
            return $this->errorResponse('Task not found.', null,Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
//            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
//            DB::rollback();
            return $this->errorResponse('Invalid or missing token.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
//            DB::rollback();
            return $this->errorResponse('Something went wrong while rejecting the task.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminReview(Request $request, $id){
        DB::beginTransaction();
        try {

            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            $user = JWTAuth::parseToken()->authenticate();


            $task = Task::findOrFail($id);


            if ($task->status === 'admin_review'||$task->status === 'verifyed'||$task->status === 'completed') {
                return $this->errorResponse('This task has already been ',$task->status,Response::HTTP_CONFLICT);
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
            return $this->errorResponse('Task status cannot be processed',null, Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found.' ,$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token has expired. Please log in again.',$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while admin review the task.',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function allPerformTask(Request $request){
//        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');

        try {
            $tasks = TaskPerformer::with([
                'task',
                'taskAttached:id,tp_id,file_url',
                'country',
                'socialTask',
                'engagement',
                'creator' => function($q){
                    $q->select('users.id as user_id', 'users.name', 'users.avatar','users.created_at');
                },
                'performer',
                'social',
            ])
                ->when($search, function($query, $search) {
                    $query->whereHas('creator', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                        $q->where('email', 'like', "%{$search}%");
                    })
                        ->orWhereHas('performer', function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                            $q->where('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('engagement', function($q) use ($search) {
                            $q->where('engagement_name', 'like', "%{$search}%");
                        });
                })
                ->where('status','pending')
                ->orderBy('created_at', 'desc')
                ->paginate(10);



            return $this->successResponse($tasks, 'All tasks fetched successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks.', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function ptapproved($id){
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $task = TaskPerformer::findOrFail($id);
            $performer=User::find($task->user_id);
            if ($task->status === 'completed') {
                return $this->errorResponse('This task has already been ',$task->status,Response::HTTP_CONFLICT);
            }
            if (!$performer) {
                return $this->errorResponse('This task does not exists.', null,Response::HTTP_NOT_FOUND);
            }

            if ($task->status === 'Pending') {
                $task->status = 'completed';
                $task->verified_by = $user->id;
                $task->save();
                $performer->earn_token+=$task->token_earned;
                $performer->save();

                $title = 'Your task is completed.';
                $body = $performer->name. ', your task is completed. You earned '.$task->token_earned.' tokens.';

                $performer->notify(new UserNotification($title, $body));
                DB::commit();
                return $this->successResponse($task, 'Task approved successfully.', Response::HTTP_OK);
            }
            DB::rollback();
            return $this->errorResponse('Task status cannot be processed',null, Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ' ,$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.',$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function ptrejectTask(Request $request,$id){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user = JWTAuth::parseToken()->authenticate();

            $task = TaskPerformer::findOrFail($id);

            if ($task->status === 'Completed') {
                return $this->errorResponse('This task has already been ',$task->status,Response::HTTP_CONFLICT);
            }
            if ($task->status === 'Rejected') {
                return $this->errorResponse('This task has already been ',$task->status,Response::HTTP_CONFLICT);
            }

            if ($task->status === 'Pending') {
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
            return $this->errorResponse('Task status cannot be processed', null,Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ' ,$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.',$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.',$e->getMessage(),Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function ptadminReview(Request $request,$id){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user = JWTAuth::parseToken()->authenticate();

            $task = TaskPerformer::findOrFail($id);

            if ($task->status === 'Admin_review') {
                return $this->errorResponse('This task has already been ',$task->status,Response::HTTP_CONFLICT);
            }
            if ($task->status === 'Rejected') {
                return $this->errorResponse('This task has already been ',$task->status,Response::HTTP_CONFLICT);
            }
            if ($task->status === 'Completed') {
                return $this->errorResponse('This task has already been ',$task->status,Response::HTTP_CONFLICT);
            }

            if ($task->status === 'Pending') {
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
            return $this->errorResponse('Task status cannot be processed', null,Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ' ,$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. Please log in again.',$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.',$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    // App For Performer
    public function availableTasksForMe(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found', null, Response::HTTP_NOT_FOUND);
            }

            if (!$user->country_id) {
                return $this->errorResponse('User country not found', null, Response::HTTP_NOT_FOUND);
            }

            $perPage = $request->query('per_page',10);
            $search = $request->query('category');
            $searchValue = $request->query('search');

//            if (strlen(trim($search)) === 0) {
//                $errors['category'] = 'Category cannot be empty';
//            }
//
//            if (strlen(trim($searchValue)) === 0) {
//                $errors['search'] = 'Search value cannot be empty';
//            }
//
//            if (!empty($errors)) {
//                return $this->errorResponse('Validation Error', $errors, Response::HTTP_BAD_REQUEST);
//            }

//            dd(TaskPerformer::where('user_id', $user->id)->pluck('task_id'));

//            $task = Task
            $tasksQuery = Task::with([
                'country:id,name,flag',
                'social:id,name,icon_url',
                'engagement:id,engagement_name',
                'creator:id,name,avatar'
            ])
                ->where('status', 'verifyed')
                ->whereColumn('quantity', '!=', 'performed')
                ->where('country_id', $user->country_id)
//                ->whereDoesntHave('taskperformers', function ($q) use ($user) {
//                    $q->where('user_id', $user->id);
//                })
                ->orderBy('created_at', 'desc');
//            dd($tasksQuery->get());

            if (!empty($search)) {
                $tasksQuery->whereHas('social', function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }

//            if (!empty($search)) {
//                $tasksQuery->where(function ($q) use ($search, $searchValue) {
//                    if (!empty($search)) {
//                        $q->whereHas('social', function ($sq) use ($search) {
//                            $sq->where('name', 'like', '%' . $search . '%');
//                        });
//                    }
//                });
//
//            }

                if (empty($searchValue)) {

                    $tasksQuery->where('description', 'like', "%{$searchValue}%")
                        ->orWhere('link', 'like', "%{$searchValue}%")
                        ->orWhere('note', 'like', "%{$searchValue}%")
                        ->orWhere('rejection_reason', 'like', "%{$searchValue}%")
                        ->orWhere('token_distributed', 'like', "%{$searchValue}%")
                        ->orWhere('per_perform', 'like', "%{$searchValue}%");

                    $tasksQuery->orWhereHas('social', function ($sq) use ($searchValue) {
                        $sq->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('engagement', function ($eq) use ($searchValue) {
                            $eq->where('engagement_name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('creator', function ($cq) use ($searchValue) {
                            $cq->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('country', function ($tc) use ($searchValue) {
                            $tc->where('name', 'like', "%{$searchValue}%");
                    });
                }

//                foreach ($tasksQuery->get() as $task) {
//                    $taskPerf = TaskPerformer::where('user_id', $user->id)->where('task_id', $task->id)->exists();
//
//
//                    dd($taskPerf);
//                }
            $tasks = $tasksQuery->paginate($perPage, [
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
                'username' => $user->name,
                'tasks' => $tasks
            ], 'All tasks fetched successfully.', Response::HTTP_OK);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->errorResponse('Token expired. Please log in again.', $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->errorResponse('Invalid or missing token.', $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function singleTaskDetails(Request $request, string $taskId)
    {
        try {
            $data = $request->validate([
                'check' => 'required',
            ]);
            if ($data['check'] == 'task') {
                $task = Task::with([
                    'country:id,name,flag',
                    'social:id,name,icon_url',
                    'engagement:id,engagement_name',
                    'creator:id,name,avatar'
                ])->findOrFail($taskId);

                $response = [
                    'task' => $task
                ];
            }elseif ($data['check'] == 'task_performer') {

            $taskPerform = TaskPerformer::with(['task','task.creator:id,name,avatar','task.social:id,name,icon_url','taskAttached','task.engagement'])->findOrFail($taskId);

//            if ($taskPerform){
//                return $this->errorResponse(null,'Task Performer not found!', Response::HTTP_NOT_FOUND);
//            }
                $response = [
                    'task_performer' => $taskPerform
                ];
            }

//            dd($taskPerform->taskAttached());
            return $this->successResponse($response, 'Task fetched successfully', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function ongoingTasks(Request $request)
    {
        try {
            $data = $request->validate([
                'per_page' => 'required|integer',
            ]);

//            $taskSave = Task::whereColumn('quantity', '>', 'performed')->paginate($data['per_page']);
            $taskSave = TaskSave::with(['user:id,name,avatar','task','task.creator:id,name,avatar','task.engagement:id,engagement_name','task.social:id,icon_url'])
                ->where('user_id',Auth::id())
                ->latest()
                ->paginate($data['per_page']);

            return $this->successResponse($taskSave, 'Task fetched successfully', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                    'error_type' => $validator->errors()->first(),
                    'errors' => $validator->errors()->first(),
                ], 422);
            }


            $user = JWTAuth::parseToken()->authenticate();

            $deleted = TaskSave::where('user_id', $user->id)
                ->where('task_id', $request->task_id)
                ->delete();

            if ($deleted) {

                DB::commit();
                return response()->json([
                    'status' => true,
                    'message' => 'Task unsaved successfully.',
                ], Response::HTTP_OK);
            }

            $saveTask = TaskSave::create([
                'user_id' => $user->id,
                'task_id' => $request->task_id,
            ]);

            DB::commit();

            return $this->successResponse($saveTask, 'Task saved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $task = Task::findOrFail($request->task_id);

            $taskPerformer = TaskPerformer::where('user_id', $user->id)->where('task_id',$task->id)->exists();

            if ($task->id = $taskPerformer){
                return $this->errorResponse(null,'You Already performed this task.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($task->quantity == $task->performed) {
                return $this->errorResponse("Task was completed. You can't perform it",null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

//            $alreadySubmitted = TaskPerformer::where('user_id', $user->id)
//                ->where('task_id', $request->task_id)
//                ->exists();
//
//            if ($alreadySubmitted) {
//                return $this->errorResponse('This task already submitted.',null, Response::HTTP_CONFLICT);
//            }

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
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
//    public function myPerformTask(Request $request){
//    $perPage = $request->query('per_page', 10);
//    $search = $request->query('search');
//    $status = strtolower($request->query('status'));
////         dd($status);
//
//    try {
//        $user = JWTAuth::parseToken()->authenticate();
//        if ($status == 'rejected' || $status == 'completed') {
//            $tasks = TaskPerformer::with([
//                'task',
//                'task.social',
//                'taskAttached:id,tp_id,file_url',
//                'country',
//                'engagement',
//                'creator' => function ($q) {
//                    $q->select('users.id as user_id', 'users.name', 'users.avatar');
//                },
//                'performer:id,name,avatar',
//            ])
//                ->when($search ?? null, function ($query, $search) {
//                    $query->whereHas('engagement', function ($q) use ($search) {
//                        $q->where('engagement_name', 'like', "%{$search}%");
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('social', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                        });
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('creator', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                        });
//                    })->orWhereHas('task', function ($q) use ($search) {
////                            $q->whereHas('creator', function ($q2) use ($search) {
//                        $q->where('description', 'like', "%{$search}%");
//                        $q->where('total_token', 'like', "%{$search}%");
//                        $q->where('token_distributed', 'like', "%{$search}%");
//                        $q->where('quantity', 'like', "%{$search}%");
////                            });
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('country', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                            $q2->where('dial_code', 'like', "%{$search}%");
//                            $q2->where('currency_code', 'like', "%{$search}%");
////                                $q2->where('quantity', 'like', "%{$search}%");
//                        });
//                    });
//                })->where('task_performers.status', $status)
//                ->where('user_id', $user->id)->orderBy('created_at', 'desc')->paginate($perPage);
//        }elseif($status == 'all'){
//            $tasks = TaskPerformer::with([
//                'task',
//                'taskAttached:id,tp_id,file_url',
//                'country',
//                'engagement',
//                'creator' => function ($q) {
//                    $q->select('users.id as user_id', 'users.name', 'users.avatar');
//                },
//                'performer:id,name,avatar',
//            ])
//                ->when($search ?? null, function ($query, $search) {
//                    $query->whereHas('engagement', function ($q) use ($search) {
//                        $q->where('engagement_name', 'like', "%{$search}%");
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('social', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                        });
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('creator', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                        });
//                    })->orWhereHas('task', function ($q) use ($search) {
////                            $q->whereHas('creator', function ($q2) use ($search) {
//                        $q->where('description', 'like', "%{$search}%");
//                        $q->where('total_token', 'like', "%{$search}%");
//                        $q->where('token_distributed', 'like', "%{$search}%");
//                        $q->where('quantity', 'like', "%{$search}%");
////                            });
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('country', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                            $q2->where('dial_code', 'like', "%{$search}%");
//                            $q2->where('currency_code', 'like', "%{$search}%");
////                                $q2->where('quantity', 'like', "%{$search}%");
//                        });
//                    });
//                })
//                ->where('user_id', $user->id)->orderBy('created_at', 'desc')->paginate($perPage);
//        }else{
//            $tasks = TaskPerformer::with([
//                'task',
//                'task.social',
//                'taskAttached:id,tp_id,file_url',
//                'country',
//                'engagement',
//                'creator' => function ($q) {
//                    $q->select('users.id as user_id', 'users.name', 'users.avatar');
//                },
//                'performer:id,name,avatar',
//            ])
//                ->when($search ?? null, function ($query, $search) {
//                    $query->whereHas('engagement', function ($q) use ($search) {
//                        $q->where('engagement_name', 'like', "%{$search}%");
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('social', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                        });
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('creator', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                        });
//                    })->orWhereHas('task', function ($q) use ($search) {
////                            $q->whereHas('creator', function ($q2) use ($search) {
//                        $q->where('description', 'like', "%{$search}%");
//                        $q->where('total_token', 'like', "%{$search}%");
//                        $q->where('token_distributed', 'like', "%{$search}%");
//                        $q->where('quantity', 'like', "%{$search}%");
////                            });
//                    })->orWhereHas('task', function ($q) use ($search) {
//                        $q->whereHas('country', function ($q2) use ($search) {
//                            $q2->where('name', 'like', "%{$search}%");
//                            $q2->where('dial_code', 'like', "%{$search}%");
//                            $q2->where('currency_code', 'like', "%{$search}%");
////                                $q2->where('quantity', 'like', "%{$search}%");
//                        });
//                    });
//                })
//                ->where('user_id', $user->id)->orderBy('created_at', 'desc')->paginate($perPage);
//        }
//
//        return $this->successResponse($tasks, 'Tasks retrieved successfully.', Response::HTTP_OK);
//
//    } catch (\Exception $e) {
//        return $this->errorResponse('Failed to fetch tasks ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
//    }
//}

    public function myPerformTask(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $status = strtolower($request->query('status'));

        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Base query
            $query = TaskPerformer::with([
                'task',
                'task.social',
                'taskAttached:id,tp_id,file_url',
                'country',
                'engagement',
                'creator:users.id,name,avatar',
                'performer:id,name,avatar',
            ])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // STATUS FILTER
            if ($status && $status !== 'all') {
                $query->where('task_performers.status', $status);
            }

            // SEARCH FILTER (grouped properly)
            if ($search) {
                $query->where(function ($q) use ($search) {

                    // Engagement name
                    $q->whereHas('engagement', function ($q2) use ($search) {
                        $q2->where('engagement_name', 'like', "%{$search}%");
                    });

                    // Task → Social
                    $q->orWhereHas('task.social', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });

                    // Task → Creator
                    $q->orWhereHas('task.creator', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });

                    // Task direct fields
                    $q->orWhereHas('task', function ($q2) use ($search) {
                        $q2->where('description', 'like', "%{$search}%")
                            ->orWhere('total_token', 'like', "%{$search}%")
                            ->orWhere('token_distributed', 'like', "%{$search}%")
                            ->orWhere('quantity', 'like', "%{$search}%");
                    });

                    // Task → Country
                    $q->orWhereHas('task.country', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('dial_code', 'like', "%{$search}%")
                            ->orWhere('currency_code', 'like', "%{$search}%");
                    });
                });
            }

            // FINAL: paginate
            $tasks = $query->paginate($perPage);

            return $this->successResponse($tasks, 'Tasks retrieved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //admin

    public function adminSupportTask(Request $request){
         $perPage = $request->query('per_page', 10);
        try {

            $data = $request->validate([
                'status' => 'nullable|string|in:task,order,user',
                'search' => 'nullable|string'
            ]);

            $status = $data['status'] ?? 'task';
            $search = $data['search'] ?? '';

//            $tasks = Task::with([
//                'country:id,name,flag',
//                'social:id,name,icon_url',
//                'engagement:id,engagement_name',
//                'reviewer:id,name,email,phone,avatar',
//                'reviewerCountry'
//            ])->where('status','admin_review')->orderBy('created_at', 'desc')->paginate($perPage,[
//                'id',
//                'sm_id','country_id','sms_id','user_id',
//                'quantity',
//                'description',
//                'link',
//                'per_perform',
//                'status',
//                'verified_by',
//                'rejection_reason',
//                'note',
//                'created_at'
//            ]);
//            $tasksPerform = TaskPerformer::with([
//                'task.engagement:id,engagement_name',
//                'performer:id,name,email,phone,avatar',
//                'reviewer:id,name,email,phone,avatar',
//                'taskAttached:id,tp_id,file_url',
//                'task:id,sms_id,country_id',
//                'task.country:id,name,flag',
//            ])
//                ->where('status', 'admin_review')
//                ->orderBy('created_at', 'desc')
//                ->paginate($perPage);
//
//            $ticketSupport = SupportTicket::with(['ticketcreator:id,name,email,phone,avatar,country_id','ticketcreator.country:id,name,flag'])->get();

            if ($status === 'task') {

                $tasks = Task::with(['country',
                    'social',
                    'engagement',
//                    'reviewer',
//                    'reviewerCountry',
                    'creator'])
                    ->where('status', 'admin_review')
                    ->when($search, function ($q) use ($search) {
                        $q->where(function ($sub) use ($search) {
                            $sub->where('id', $search)
                                ->orWhereHas('reviewer', function ($rev) use ($search) {
                                    $rev->where('name', 'like', "%$search%")
                                        ->orWhere('email', 'like', "%$search%");
                                });
                        });
                    })
                    ->orderBy('updated_at', 'desc')->latest()
                    ->paginate($perPage);

                $tasks->getCollection()->transform(function ($item) use ($status) {
                    $item->status = $status; // new attribute
                    return $item;
                });

                return $this->successResponse($tasks, "Tasks retrieved",200);
            }

            if ($status === 'order') {

                $taskPerform = TaskPerformer::with(['task', 'performer','performer.taskPerformerSocialAc.social', 'reviewer', 'taskAttached','task.engagement'])
                    ->where('status', 'admin_review')
                    ->when($search, function ($q) use ($search) {
                        $q->where(function ($sub) use ($search) {
                            $sub->where('id', $search)
                                ->orWhereHas('performer', function ($p) use ($search) {
                                    $p->where('name', 'like', "%$search%")
                                        ->orWhere('email', 'like', "%$search%")
                                        ->orWhere('phone', 'like', "%$search%");
                                });
                        });
                    })
                    ->orderBy('updated_at', 'desc')->latest()
                    ->paginate($perPage);

                $taskPerform->getCollection()->transform(function ($item) use ($status) {
                    $item->status = $status; // new attribute
                    return $item;
                });

                return $this->successResponse($taskPerform, "Order retrieved",200);
            }

            if ($status === 'user') {

                $ticketSupport = SupportTicket::with(['ticketcreator', 'ticketcreator.country'])
                    ->when($search, function ($q) use ($search) {
                        $q->where(function ($sub) use ($search) {
                            $sub->where('id', $search)
                                ->orWhere('title', 'like', "%$search%")
                                ->orWhereHas('ticketcreator', function ($tc) use ($search) {
                                    $tc->where('name', 'like', "%$search%")
                                        ->orWhere('email', 'like', "%$search%")
                                        ->orWhere('phone', 'like', "%$search%");
                                });
                        });
                    })
                    ->orderBy('updated_at', 'desc')
                    ->paginate($perPage);

                $ticketSupport->getCollection()->transform(function ($item) use ($status) {
                    $item->status = $status;
                    $item->attachments = array($item->attachments);
                    return $item;
                });

                return $this->successResponse($ticketSupport, "Tickets retrieved",200);
            }



//            return $this->successResponse(['task'=>$tasks, 'task perform'=> $tasksPerform, 'ticket support'=> $ticketSupport], 'Tasks retrieved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminApproveTask($id){
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $task = Task::findOrFail($id);

            if ($task->status === 'verifyed') {
                return $this->errorResponse('This task is already approved.',null, Response::HTTP_UNPROCESSABLE_ENTITY);
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
            return $this->errorResponse('This task is already approved.', null,Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found.',$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired.',$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Token Invalid.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()->first(),
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();

            $task = Task::findOrFail($id);

            if ($task->status === 'rejected') {
                return $this->errorResponse('This task is already rejected.',null, Response::HTTP_FORBIDDEN);
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
            return $this->errorResponse('This task is already rejected.',null, Response::HTTP_FORBIDDEN);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ',$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. ',$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Token Invalid. ',$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function adminTaskDetails(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'type' => 'nullable|string'
            ]);
            if (($data['type']) ?? null == 'user') {
//                $support = SupportTicket::with(['reviewer','reviewer.country'])->find($id);


                $task = TaskPerformer::with(['task:*','taskAttached:*','engagement:*','reviewer:*','reviewer.country:*','country:*','creator:*','taskPerformerSocialAc:*','socialTask:*'])->find($id);

//                dd($task);

                $social = SocialAccount::with('social:*')->where('user_id',$task->user_id)->first();

                $tasks = [
//                    $task->social => $social,
//                    'social'=>$social,
                ];
                return $this->successResponse($task, 'Task details.', Response::HTTP_OK);
            }
            else{
                $task = Task::with(['country:id,name,flag',
                    'creator',
                    'social:id,name,icon_url',
                    'reviewer:id,name,email,phone,country_id,avatar',
                    'reviewer.country:id,name',
                    'engagement',
                    'performers.taskAttached',
                    'taskFiles', 'users', 'socialAccount'
                ])->find($id);
                return $this->successResponse($task, 'Task details.', Response::HTTP_OK);
            }




        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function adminSupportPerformTask(Request $request,$id){
//         $perPage = $request->query('per_page', 10);
        try {
            $task = TaskPerformer::with(['task:*','taskAttached:*','task.engagement:*','reviewer:*','reviewer.country:*','performer:*'])->find($id);


            $social = SocialAccount::with('social:*')->where('user_id',$task->user_id)->first();

            $tasks = [
                $task,
                'social'=>$social,
            ];

            return $this->successResponse($tasks, 'Tasks retrieved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch tasks ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminApprovedSPerformTask($id){
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $task = TaskPerformer::findOrFail($id);
            $performer=User::find($task->user_id);
            if ($task->status === 'completed') {
                return $this->errorResponse('This task is already approved.', null,Response::HTTP_FORBIDDEN);
            }
            if (!$performer) {
                return $this->errorResponse('Performer not found.',null, Response::HTTP_NOT_FOUND);
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
            return $this->errorResponse('This task is already approved.',null, Response::HTTP_FORBIDDEN);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return $this->errorResponse('Task not found. ',$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token expired. ',$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Token Invalid. ',$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function adminRejectedSPerformTask(Request $request, $id){
        DB::beginTransaction();
         try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $user = JWTAuth::parseToken()->authenticate();

            $task = TaskPerformer::findOrFail($id);


            if ($task->status === 'Completed') {
                return $this->errorResponse('This task is already approved.',null, Response::HTTP_CONFLICT);
            }
            if ($task->status === 'Rejected') {
                return $this->errorResponse('This task is already rejected.',null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if ($task->status === 'Admin_review') {
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
            return $this->errorResponse('This task is already rejected.',null, Response::HTTP_FORBIDDEN);

        } catch (ModelNotFoundException $e) {
             DB::rollback();
             return $this->errorResponse('Task not found. ',$e->getMessage(), Response::HTTP_NOT_FOUND);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
             DB::rollback();
             return $this->errorResponse('Token expired. ',$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
             DB::rollback();
             return $this->errorResponse('Token Invalid. ',$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Throwable $e) {
             DB::rollback();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
            return $this->errorResponse('Something went wrong. ' ,$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function taskDetails($taskId)
    {
        try {
            $check = Task::findOrFail($taskId);
            if (!$check){
                return $this->errorResponse('Task not found.',null, Response::HTTP_NOT_FOUND);
            }
            $task = Task::with(['reviewer','reviewer.country','country','engagement','social'])->where('tasks.id', $taskId)
                ->first();

            if ($task->performed == $task->quantity) {
                $task->status = 'completed';
            }
            elseif ($task->status === 'rejected') {
                $task->status = 'rejected';
            }else{
                $task->status = 'ongoing';
                $task->progress = ($task->performed / $task->quantity) * 100;
            }
//            if ($task->quantity > $task->performed){
//                $task->status = 'ongoing';
//                $task->progress = ($task->performed / $task->quantity) * 100;
//            }elseif($task->quantity == $task->performed){
//                $task->status = 'completed';
//            }else{
//                $task->status = 'rejected';
//            }

//            $isCompleted = ($task->quantity == $task->performed);
//            $status = $isCompleted ? 'Complete Task' : 'Active Task';
//
//            $response = [
//                $status => $task,
//            ];
//
//            if ($isCompleted) {
//                $response['Total Performed Task'] = $task->performed;
//                $response['Token Distribution'] = $task->token_distribution ?? 0;
//            }
//
//            if ($task->status === 'rejected') {
//                $response['Rejected Task'] = $task->rejection_reason;
//            }

            return $this->successResponse($task, 'Task details', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function orderManagement(Request $request)
    {
        try {
            $data = $request->validate([
                'search' => 'nullable|string',
                'status' => 'required',
                'tags'=>'required'
            ]);
            $search = $data['search'] ?? null;
            if ($data['tags']=='task_management') {
                if ($data['status'] == 'completed') {
                    $completeTask = Task::with(['creator', 'engagement','reviewer'])->whereColumn('quantity', '=', 'performed')->latest()
                        ->when($search, function($query, $search) {
                            $query->where(function($q) use ($search) {
                                $q->where('description', 'like', "%{$search}%")
                                    ->orWhere('link', 'like', "%{$search}%");

                                $q->orWhereHas('creator', function($q2) use ($search) {
                                    $q2->where('name', 'like', "%{$search}%");
                                    $q2->orWhere('email', 'like', "%{$search}%");
                                });

                                $q->orWhereHas('engagement', function($q3) use ($search) {
                                    $q3->where('engagement_name', 'like', "%{$search}%");
                                });
                            });
                        })->paginate(10);
                    $completeTask->status = 'completed';
                    return $this->successResponse($completeTask, 'All Orders', Response::HTTP_OK);
                } elseif ($data['status'] == 'rejected') {
                    $rejected = Task::with(['creator', 'engagement'])->where('status', 'rejected')->latest()
                        ->when($search, function($query, $search) {
                            $query->where(function($q) use ($search) {
                                $q->where('description', 'like', "%{$search}%")
                                    ->orWhere('link', 'like', "%{$search}%");

                                $q->orWhereHas('creator', function($q2) use ($search) {
                                    $q2->where('name', 'like', "%{$search}%");
                                    $q2->orWhere('email', 'like', "%{$search}%");
                                });

                                $q->orWhereHas('engagement', function($q3) use ($search) {
                                    $q3->where('engagement_name', 'like', "%{$search}%");
                                });
                            });
                        })->paginate(10);
                    $rejected->status = 'rejected';
                    return $this->successResponse($rejected, 'All Orders', Response::HTTP_OK);
                } elseif ($data['status'] == 'ongoing') {
                    $activeTask = Task::with(['creator', 'engagement'])->whereColumn('quantity', '>', 'performed')->latest()
                        ->when($search, function($query, $search) {
                            $query->where(function($q) use ($search) {
                                $q->where('description', 'like', "%{$search}%")
                                    ->orWhere('link', 'like', "%{$search}%");

                                $q->orWhereHas('creator', function($q2) use ($search) {
                                    $q2->where('name', 'like', "%{$search}%");
                                    $q2->orWhere('email', 'like', "%{$search}%");
                                });

                                $q->orWhereHas('engagement', function($q3) use ($search) {
                                    $q3->where('engagement_name', 'like', "%{$search}%");
                                });
                            });
                        })->paginate(10);
                    $activeTask->status = 'ongoing';
                    return $this->successResponse($activeTask, 'All Orders', Response::HTTP_OK);
                }

            }
            elseif ($data['tags']=='order_management') {
                if ($data['status'] == 'completed_order') {
                    $completedOrder = TaskPerformer::with(['performer', 'task', 'task.engagement'])->where('status', 'completed')->latest()
                        ->when($search, function ($query, $search) {
                            $query->where(function ($q) use ($search) {

                                // Search by performer
                                $q->whereHas('performer', function ($p) use ($search) {
                                    $p->where('name', 'like', "%{$search}%");
                                    $p->orWhere('email', 'like', "%{$search}%");
                                })

                                    // OR search by task
//                                    ->orWhereHas('task', function ($t) use ($search) {
//                                        $t->where('title', 'like', "%{$search}%");
//                                    })

                                    // OR search by task engagement
                                    ->orWhereHas('task.engagement', function ($e) use ($search) {
                                        $e->where('engagement_name', 'like', "%{$search}%");
                                    });
                            });
                        })->paginate(10);
                    $response = $completedOrder;
                    return $this->successResponse($completedOrder, 'All Orders', Response::HTTP_OK);
                } elseif ($data['status'] == 'rejected_order') {
                    $rejectedOrder = TaskPerformer::with(['performer', 'task', 'task.engagement'])->where('status', 'rejected')->latest()
                        ->when($search, function ($query, $search) {
                            $query->where(function ($q) use ($search) {

                                // Search by performer
                                $q->whereHas('performer', function ($p) use ($search) {
                                    $p->where('name', 'like', "%{$search}%");
                                    $p->orWhere('email', 'like', "%{$search}%");
                                })

                                    // OR search by task
//                                    ->orWhereHas('task', function ($t) use ($search) {
//                                        $t->where('title', 'like', "%{$search}%");
//                                    })

                                    // OR search by task engagement
                                    ->orWhereHas('task.engagement', function ($e) use ($search) {
                                        $e->where('engagement_name', 'like', "%{$search}%");
                                    });
                            });
                        })->paginate(10);
                    $response = $rejectedOrder;
                    return $this->successResponse($rejectedOrder, 'All Orders', Response::HTTP_OK);
                }
            }
            return $this->errorResponse('Something went wrong. ',null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function orderDetails($orderId)
    {
        try {
            $order = TaskPerformer::with([
                'task',
                'task.social',
                'taskAttached',
                'country',
                'reviewer',
                'reviewer.country',
                'engagement',
                'creator'
            ])->where('task_performers.id', $orderId)
                ->first();


                if ($order->task_status === 'rejected') {
                    $order->rejected_reason = $order->rejection_reason;

                }

            return $this->successResponse($order, 'All Orders', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

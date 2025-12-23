<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Exceptions\JWTException;

class FinancialController extends Controller
{
    public function financialList(Request $request)
    {
        try {
//            $data = $request->validate([
//                'search' => 'nullable|string',
//            ]);
//            $finance = TaskPerformer::with([
//                'performer:id,name,email,phone,avatar',
//                'task:id,country_id,sms_id,total_price',
//                'task.engagement:id,engagement_name',
//                'task.country:id,name,flag'
//            ])->where('status', 'pending')
//                ->paginate('10');
//
//            $completed = TaskPerformer::with([
//                'performer:id,name,email,phone,avatar',
//                'task:id,country_id,sms_id,total_price',
//                'task.engagement:id,engagement_name',
//                'task.country:id,name,flag'
//            ])->where('status', 'completed')
//                ->paginate('10');
//
//            $blocked = TaskPerformer::with([
//                'performer:id,name,email,phone,avatar',
//                'task:id,country_id,sms_id,total_price',
//                'task.engagement:id,engagement_name',
//                'task.country:id,name,flag'
//            ])->where('status', 'blocked')
//                ->paginate('10');
//
//            $searchValue = trim($data['search'] ?? '');
//
//            $searchdata = User::where(function ($q) use ($searchValue) {
//                $q->where('name', 'like', '%' . $searchValue . '%')   // partial match
//                ->orWhere('email', $searchValue);                  // exact match
//            })
//                ->whereIn('role', ['performer', 'brand'])
//                ->get();
//
////            dd($searchdata);
//
//            return $this->successResponse([
//                'Pending Approval' => $finance,
//                'Completed' => $completed,
//                'Blocked' => $blocked,
//                'search_value' => $data['search'],
//                'search' => $searchdata,
//            ], 'Status updated successfully', Response::HTTP_OK);

            $data = $request->validate([
                'search' => 'nullable|string',
                'status' => 'nullable|string|in:pending,completed,blocked'
            ]);

            $searchValue = trim($data['search'] ?? '');




//                $getTasksByStatus = function ($status) use ($searchValue) {
//                    $task = Task::with('creator')->first();
//                    $taskPer = TaskPerformer::with('performer')->first();
//                    if ($task->creator->id == $taskPer->performer->id) {
//                        $query = Task::with([
//                            'creator:id,name,email,phone,avatar',
////                    'task:id,country_id,sms_id,total_price',
//                            'engagement:id,engagement_name',
//                            'country:id,name,flag'
//                        ])->where('status', $status)->latest();
//
//                        if ($searchValue !== '') {
//                            $query->whereHas('creator', function ($q) use ($searchValue) {
//                                $q->where('name', 'like', '%' . $searchValue . '%')
//                                    ->orWhere('email', $searchValue);
//                            });
//                        }
//
//                        return $query->paginate(10);
//                    }
//                };

            $getTasksByStatus = function ($status) use ($searchValue) {

                $query = Task::with([
                    'creator:id,name,email,phone,avatar',
                    'engagement:id,engagement_name',
                    'country:id,name,flag'
                ])
                    ->where('status', $status)
                    ->whereHas('performers', function ($q) {
                        $q->whereColumn('task_performers.user_id', 'tasks.user_id');
                    })
                    ->latest();

                if ($searchValue !== '') {
                    $query->whereHas('creator', function ($q) use ($searchValue) {
                        $q->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('email', $searchValue);
                    });
                }

                return $query->paginate(10);
            };



            // Get tasks by status
//            $pendingTasks = $getTasksByStatus('pending');
//            $completedTasks = $getTasksByStatus('completed');
//            $blockedTasks = $getTasksByStatus('blocked');

            $searchTask = $getTasksByStatus($data['status'] ?? 'pending');

            // Optional: search users across roles
            $searchUsers = [];
            if ($searchValue !== '') {
                $searchUsers = User::whereIn('role', ['performer', 'brand'])
                    ->where(function ($q) use ($searchValue) {
                        $q->where('name', 'like', '%' . $searchValue . '%')
                            ->orWhere('email', $searchValue);
                    })
                    ->get();
            }

            return $this->successResponse($searchTask,'Status retrieved successfully', Response::HTTP_OK);

        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ' ,$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function searchUser(Request $request)
    {
        try {
            $data = $request->validate([
                'search' => 'required'
            ]);

            $search = User::where('name', 'like', '%' . $data['search'] . '%')
                ->orWhere('email', 'like', '%' . $data['search'] . '%')
                ->whereIn('role',['performer','brand'])
                ->latest()
                ->paginate('10');

            return $this->successResponse(['search' => $data['search'], 'user'=>$search, 'count'=>$search->count()], 'Search List', Response::HTTP_OK);
        }catch (\Exception $e){
            return $this->errorResponse('Something went wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function updateFinancial(Request $request, $taskPerformer_id)
    {
        DB::beginTransaction();
        try {
            $tp = Task::with('creator')->where('id', $taskPerformer_id)->first();

            $data = $request->validate([
                'status' => 'required|in:completed,blocked',
            ]);

            $data['status'] = $data['status'] === 'completed' ? 'completed' : 'blocked';
            $data['verified_by'] = Auth::id();

            $tp->update($data);

            $withdrawalStatus = $data['status'] === 'completed' ? '1' : '0';

            $tp->creator?->update([
                'withdrawal_status' => $withdrawalStatus
            ]);

            DB::commit();

            return $this->successResponse($tp, 'Status updated successfully', Response::HTTP_OK);
        }
        catch (JWTException $e){
            DB::rollback();
            return $this->errorResponse('Something went wrong. ' ,$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        catch (\Exception $e){
            DB::rollback();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

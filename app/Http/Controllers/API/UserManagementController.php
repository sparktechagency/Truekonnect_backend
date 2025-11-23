<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class UserManagementController extends Controller
{
    /* Rubayet */
    public function index()
    {
        try {
            $userActive = User::with('country:id,flag')
                ->where('role','brand')
                ->orWhere('role','performer')
                ->where('status','active')
//                ->get();
            ->paginate(10);
            $userBanned = User::with('country:id,flag')
                ->where('role','brand')
                ->orWhere('role','performer')
                ->where('status','banned')
//                ->get();
            ->paginate(10);
            return $this->successResponse(['active users'=>$userActive,'user banned'=>$userBanned],'All users retrieved successfully',Response::HTTP_OK);
        } catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (TokenExpiredException $exception){
            return $this->errorResponse('Token expired. '.$exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function performerDetails($userId)
    {
        try {
            $userList = User::with('country:id,flag')->find($userId);
            $referredUser = User::with('country:id,flag')->where('referral_id', $userId)->get();
            $totalWithdrawal = Withdrawal::where('user_id', $userId)->where('status', 'success')->sum('amount');
            $totalEarnedToken = User::where('id', $userId)->sum('earn_token');
            $totalTaskPerform = TaskPerformer::where('user_id', $userId)->count();

            $brandDetails = [];
            if ($userList->role == 'brand') {
                $completedOrder = Task::where('user_id', $userId)->where('status', 'active')
                    ->whereColumn('quantity', '=', 'performed')->count();

                $ongoingOrder = Task::where('user_id', $userId)->where('status', 'active')
                    ->whereColumn('quantity', '>', 'performed')->count();

            $totalUserPaid = Task::join('payments', 'payments.task_id', '=', 'tasks.id')
                ->where('tasks.user_id', $userId)
                ->where('tasks.status', 'active')
                ->whereColumn('payments.user_id', '!=', 'tasks.user_id')
                ->count();


                $brandDetails = [
                    'completed' => $completedOrder,
                    'ongoing' => $ongoingOrder,
                    'total' => $totalUserPaid,
                ];
            }
            return $this->successResponse([
                'User Details' => $userList,
                'Referred User' => $referredUser,
                'Total Withdrawal' => $totalWithdrawal,
                'Total Earned Token' => $totalEarnedToken,
                'Total Task Perform' => $totalTaskPerform,
                'Brand Details' => $brandDetails,
            ], 'All users retrieved successfully', Response::HTTP_OK);
        }catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changeStatus(Request $request, $userId)
    {
        try {
            $data = $request->validate([
                'rejection_reason' => 'sometimes|string',
            ]);
            $user = User::find($userId);

            if ($user->status == 'active') {
                $user->status = 'banned';
                $user->rejection_reason = $data['rejection_reason'];

                $title = 'Status has been updated!';
                $body = 'Sorry, your account has been banned. Reason: '. ($data['rejection_reason'] ?? 'Not specified');
            } else {
                $user->status = 'active';
                $user->rejection_reason = null;

                $title = 'Status has been updated!';
                $body = 'Your account has been activated.';
            }

            $user->save();

            $user->notify(new UserNotification($title, $body));

            return $this->successResponse([$user], 'User status updated successfully', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sendToken(Request $request, $userId)
    {
        try {
            $data = $request->validate([
                'earn_token' => 'numeric'
            ]);

            $user = User::find($userId);

            $user->earn_token += $data['earn_token'];

            $user->save();

            $title = 'You have been earned!';
            $body = 'You earned '.$user->earn_token.' token';

            $user->notify(new UserNotification($title, $body));


    //        $data = $request->validate([
    //            'token_earned' => 'numeric'
    //        ]);
    //
    //        $user = TaskPerformer::where('user_id',$userId)->first();
    //
    //        $user->token_earned += $data['token_earned'];
    //
    //        $user->save();

//            $title = 'You have been earned!';
//            $body = 'You earned '.$user->token_earned.' token';
//
//            $user->notify(new UserNotification($title, $body));

            return $this->successResponse([$user], 'Token generated successfully', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

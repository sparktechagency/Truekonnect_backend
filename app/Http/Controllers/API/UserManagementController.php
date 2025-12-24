<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class UserManagementController extends Controller
{
    /* Rubayet */
    public function index(Request $request)
    {
        try {
            $data = $request->validate([
                'search' => 'nullable|string',
                'status'=> 'nullable|string',
            ]);
            $userActive = User::with('country:id,name,flag')->whereIn('role',['performer','brand'])->performerOrBrand($data['status'] ?? 'active')
                ->withCount('referral')->search($data['search'] ?? null)->latest()
//                ->where('role','brand')
//                ->orWhere('role','performer')
//                ->where('status','active')
//                ->get();
            ->paginate(10);
//            $userBanned = User::with('country:id,flag')
//                ->where('role','brand')
//                ->orWhere('role','performer')
//                ->where('status','banned')
////                ->get();
//            ->paginate(10);
            return $this->successResponse($userActive,'All users retrieved successfully',Response::HTTP_OK);
        } catch (TokenExpiredException $exception){
            return $this->errorResponse('Token expired. ',$exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function performerDetails($userId)
    {
        try {
            $userList = User::with('country:id,name,flag')->find($userId);

            if(!$userList){
                return $this->errorResponse('User not found',null, Response::HTTP_NOT_FOUND);
            }
            $referredUser = User::with('country:id,name,flag')->where('referral_id', $userId)->inRandomOrder()->take(6)->get();
            $totalWithdrawal = Withdrawal::where('user_id', $userId)->where('status', 'success')->sum('amount');
            $totalEarnedToken = User::where('id', $userId)->sum('earn_token');
            $totalTaskPerform = TaskPerformer::where('user_id', $userId)->count();

            $brandDetails = [];
            if ($userList->role == 'brand') {
                $completedOrder = Task::where('user_id', $userId)->where('status', 'active')
                    ->whereColumn('quantity', '=', 'performed')->count();

                $ongoingOrder = Task::where('user_id', $userId)->where('status', 'active')
                    ->whereColumn('quantity', '>', 'performed')->count();

                $payment = Payment::with('user:id,name,email,avatar')
                    ->whereHas('user', fn($q) => $q->where('referral_id', $userId))
                    ->where('status', 'completed')
                    ->orderBy('created_at')
                    ->distinct('user_id')
                    ->count();

                $referralsWithdrawals = Withdrawal::with('user:id,name,email,avatar')
                    ->whereHas('user', fn($q) => $q->where('referral_id', $userId))
                    ->where('status', 'completed')
                    ->orderBy('created_at')
                    ->distinct('user_id')
                    ->count();

            $totalUserPaid = $payment + $referralsWithdrawals;


                $brandDetails = [
                    'completed' => $completedOrder,
                    'ongoing' => $ongoingOrder,
                    'total' => $totalUserPaid,
                ];
            }
            return $this->successResponse([
                'user_details' => $userList,
                'referred_user' => $referredUser,
                'total_withdrawal' => $totalWithdrawal,
                'total_earned_token' => $totalEarnedToken,
                'total_task_perform' => $totalTaskPerform,
                'brand_details' => $brandDetails,
            ], 'User retrieved successfully', Response::HTTP_OK);
        }catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function allReferrals($userId)
    {
        $referredUser = User::with('country:id,name,flag')->where('referral_id', $userId)->paginate(10);
        return $this->successResponse($referredUser,'All users retrieved successfully',Response::HTTP_OK);
    }

    public function changeStatus(Request $request, $userId)
    {
        try {
            $data = $request->validate([
                'rejection_reason' => 'sometimes|string',
            ]);
            $user = User::find($userId);


//            dd($user);
            if ($user->status == 'Not Banned') {
                $user->status = 'banned';
                $user->rejection_reason = $data['rejection_reason'];
                $user->save();

                $title = 'Status has been updated!';
                $body = 'Sorry, your account has been banned. Reason: '. ($data['rejection_reason'] ?? 'Not specified');
            } else {
                $user->status = 'active';
                $user->rejection_reason = null;
                $user->save();

                $title = 'Status has been updated!';
                $body = 'Your account has been activated.';
            }



            $user->notify(new UserNotification($title, $body));

            return $this->successResponse([$user], 'User status updated successfully', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sendToken(Request $request, $userId)
    {
        try {
            $data = $request->validate([
                'earn_token' => 'numeric'
            ]);

            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('User not found',null, Response::HTTP_NOT_FOUND);
            }

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
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

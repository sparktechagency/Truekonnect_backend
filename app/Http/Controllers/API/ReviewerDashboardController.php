<?php

namespace App\Http\Controllers\API;

use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Http\Request;
use App\Models\SocialAccount;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ReviewerDashboardController extends Controller
{
    public function allVerificationRequest(Request $request){
        try {
            $data = $request->validate([
                'search'   => 'nullable|string',
                'per_page' => 'nullable|integer',
            ]);

            $search = $data['search'] ?? null;

            $sc = SocialAccount::with(['user:id,name,avatar,email,role,phone,country_id','user.country:id,name,flag','social:id,name,icon_url'])
                ->where('status','pending')
                ->when($search ?? null, function ($query, $search) {
                    $query->where(function($q) use ($search) {
                        // Search in SocialAccount fields
                        $q->where('profile_name', 'like', "%{$search}%")
//                            ->orWhere('profile_image', 'like', "%{$search}%")
                            // Search in related user
                            ->orWhereHas('user', function ($q2) use ($search) {
                                $q2->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            // Search in related social
                            ->orWhereHas('social', function ($q3) use ($search) {
                                $q3->where('name', 'like', "%{$search}%");
                            });
                    });
                })->latest()
                ->paginate($data['per_page'] ?? 10, ['id', 'user_id', 'sm_id', 'profile_name','note', 'profile_image', 'status']);

            return $this->successResponse($sc,'All Verification Request',Response::HTTP_OK);
        }catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    public function viewSocialAccountVerify($id){
        try {
            $check = SocialAccount::find($id);

            if(!$check){
                return $this->errorResponse('Social Account not found',null, Response::HTTP_NOT_FOUND);
            }

            $sc=SocialAccount::with(['user:id,name,avatar,withdrawal_status','social:id,name,icon_url'])
                ->where(['id'=>$id,'status'=>'pending'])
                ->paginate(10,['id', 'user_id', 'sm_id', 'profile_name','note', 'profile_image', 'status']);

            return $this->successResponse($sc,'Data retrieved successfully', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong' ,$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    public function VerifySocialAccount(Request $request, $socialId)
    {
        DB::beginTransaction();
        try {
            // Validate request data
//            $validator = Validator::make($request->all(), [
//                'id' => 'required|exists:social_accounts,id',
//                'status' => 'required|in:pending,verified',
//                'withdrawal' => 'required|in:0,1',
//            ]);

//            if ($validator->fails()) {
//                return response()->json([
//                    'status' => false,
//                    'message' => 'Validation failed.',
//                    'errors' => $validator->errors()->first(),
//                ], 422);
//            }

            $verifyBy = JWTAuth::parseToken()->authenticate();
//            dd(Auth::user());


            $sa = SocialAccount::findOrFail($socialId);

            if (!$sa){
                return $this->errorResponse('Social Account not found', null,Response::HTTP_NOT_FOUND);
            }

            $sa->status = 'verified';
            $sa->verification_by = Auth::id();
            $sa->verified_at = Now();
            $sa->save();


            $user = User::findOrFail($sa->user_id);
            $user->withdrawal_status = 1;
            $user->verification_by = Auth::id();
            $user->save();

            $title = $sa->social->name . ' is verified';
            $body = 'Hi ' . $sa->User->name . ', your account is verified. You can withdrawal now';

            //Verification
            $user->notify(new UserNotification($title, $body,'verification'));

            DB::commit();

            return $this->successResponse([
                'social_account' => $sa,
                'user' => $user],'Social account verified successfully.',Response::HTTP_OK);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollBack();
            return $this->errorResponse('Token error. ',$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->errorResponse('Record not found. ',$e->getMessage(),Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rejectSocialAccount(Request $request,$socialId)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
//                'id'                => 'required|exists:social_accounts,id',
//                'status'            => 'required|in:rejected',
//                'withdrawal'        => 'required|in:0,1',
                'rejection_reason'  => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->first(),
                ], 422);
            }

            $verifyBy = JWTAuth::parseToken()->authenticate();


            $sa = SocialAccount::findOrFail($socialId);
            $sa->status = 'rejected';
            $sa->verification_by = Auth::id();
            $sa->verified_at = now();
            $sa->rejection_reason = $request->rejection_reason;
            $sa->save();

            // Update related user
            $user = User::findOrFail($sa->user_id);
            $user->verification_by = null;
            $user->withdrawal_status = '0';
            $user->save();

            $title = $sa->social->name . ' is rejected';
            $body = 'Hi ' . $sa->User->name . ', your account is rejected. Reason: '. $sa->rejection_reason;

            //Verification
            $user->notify(new UserNotification($title, $body,'verification'));

            DB::commit();

            return $this->successResponse(['social_account' => $sa, 'user' => $user],'Social account rejected.',Response::HTTP_OK);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            DB::rollBack();
            return $this->errorResponse('Token error. ',$e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->errorResponse('Record not found. ',$e->getMessage(),Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function dashboardHistory()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
//            $totalPendingAccounts = User::whereIn('role',['performer','brand'])->where('status','pending')->count();
            $totalPendingAccounts = SocialAccount::where('status','pending')->count();
            $totalPendingOrders = TaskPerformer::where('status','pending')->count();
            $totalPendingTask = Task::where('status','pending')->count();

            $totalVerified = User::where('verification_by', Auth::id())->count();
            $totalVerifiedTask = Task::where('verified_by', Auth::id())->count();
            $totalVerifiedOrder = TaskPerformer::where('verified_by', Auth::id())->count();

            $totalPending = $totalPendingAccounts + $totalPendingOrders + $totalPendingTask;
            $totalVerifiedAll = $totalVerified + $totalVerifiedTask + $totalVerifiedOrder;

            $overallPerformance = ($totalPending + $totalVerifiedAll) > 0
                ? round(($totalVerifiedAll / ($totalPending + $totalVerifiedAll)) * 100)
                : 0;

            $notifications = $user->notifications->map(function ($n) {
                $sender = null;
                if (isset($n->data['sender_id'])) {
                    $sender = User::select('id','name','email','avatar')
                        ->find($n->data['sender_id']);
                }
                return [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? null,
                    'body' => $n->data['body'] ?? null,
                    'sender' => $sender,
                    'read_at' => $n->read_at ? $n->read_at->format('M d, Y h:i:s A') : null,
                    'created_at' => $n->created_at->format('M d, Y h:i:s A'),
                ];
            });
            return $this->successResponse([
                'totalPendingAccounts' => $totalPendingAccounts,
                'totalPendingOrders' => $totalPendingOrders,
                'totalPendingTask' => $totalPendingTask,
                'total_verified_accounts' => $totalVerified,
                'total_verified_task' => $totalVerifiedTask,
                'total_verified_order' => $totalVerifiedOrder,
                'overallPerformance' => $overallPerformance,
                'recentActivity' => $notifications,
            ],'Dashboard History',Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

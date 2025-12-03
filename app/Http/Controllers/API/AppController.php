<?php

namespace App\Http\Controllers\API;

use App\Models\Payment;
use App\Models\SocialMedia;
use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SocialAccount;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AppController extends Controller
{

    public function updateProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480', // 20 MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->first(),
                ], 422);
            }

            $user = JWTAuth::user();

            if ($request->has('name') && $request->name !== NULL) {
                $user->name = $request->name;
            }

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('avatars', $filename, 'public');

                // delete old avatar if exists and not default
                if ($user->avatar && $user->avatar !== 'avatars/default_avatar.png') {
                    Storage::disk('public')->delete($user->avatar);
                }

                $user->avatar = $path;
            }

            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully.',
                'user' => $user,
            ], 200);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function brandHomepage(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $complete = Task::where('user_id', $user->id)->whereColumn('quantity','=','performed')->count();
            $ongoing = Task::where('user_id', $user->id)->whereColumn('quantity','>','performed')->count();
            $recentTask = Task::with('social:id,name,icon_url')->where('user_id', $user->id)->whereColumn('quantity','>','performed')
                ->get(['id','sm_id']);
            $payment = Payment::with('user:id,name,email,avatar')
                ->whereHas('user', fn($q) => $q->where('referral_id', $user->id))
                ->where('status', 'completed')
                ->orderBy('created_at')
                ->distinct('user_id')
                ->count('user_id');

            $referralsWithdrawals = Withdrawal::with('user:id,name,email,avatar')
                ->whereHas('user', fn($q) => $q->where('referral_id', $user->id))
                ->where('status', 'completed')
                ->orderBy('created_at')
                ->distinct('user_id')
                ->count('user_id');

            $userPaid = $payment + $referralsWithdrawals;

            $taskPerformerGotPaid = TaskPerformer::with(['performer:id,name,email,avatar','task:id,sm_id','task.social:id,name'])
                ->where('status', 'completed')->whereHas('task', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->get();

            $totaltaskPerformerGotPaid = TaskPerformer::with(['performer:id,name,email,avatar','task:id,sm_id','task.social:id,name'])
                ->where('status', 'completed')->whereHas('task', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->count();

            $totalTokenDistribution = Task::where('user_id',$user->id)->whereColumn('quantity','=','performed')->sum('token_distributed');
            return $this->successResponse([
                'user_name' => $user->name,
                'complete' => $complete,
                'ongoing' => $ongoing,
                'total_users_paid' => $totaltaskPerformerGotPaid,
                'recent_tasks' => $recentTask,
                'totaltaskPerformerGotPaid' => $totaltaskPerformerGotPaid,
                'taskPerformerGotPaid' => $taskPerformerGotPaid,
                'totalTokenDistribution' => $totalTokenDistribution,
            ],'Brand home page updated successfully.', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function completedTasks(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $completeOrders = Task::with('social:id,name,icon_url')->where('user_id',$user->id)->whereColumn('quantity','=','performed')->
            get(['id','sm_id','performed']);

            return $this->successResponse([
                'complete' => $completeOrders,
            ],"Completed tasks", Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function ongoingTasks(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $completeOrders = Task::with('social:id,name,icon_url')->where('user_id',$user->id)->whereColumn('quantity','>','performed')->
            get(['id','sm_id']);

            return $this->successResponse([
                'ongoing' => $completeOrders,
            ],"Completed tasks", Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function orderDetails(Request $request,$taskId)
    {
       try{
           $user = JWTAuth::parseToken()->authenticate();

           $task = Task::with(['social:id,name,icon_url'])
               ->where('id', $taskId)->where('user_id',$user->id)
               ->first(['id','sm_id','total_token','token_distributed','description','link','performed','created_at','total_price','quantity']);

           if ($task->quantity > 0) {
               $progressPercentage = ($task->performed / $task->quantity) * 100;
               $progressPercentage = round($progressPercentage);
           } else {
               $progressPercentage = 0;
           }

           $taskStatus = ($task->quantity == $task->performed)
               ? 'completed'
               : 'ongoing';

           $task->status = $taskStatus;
           $task->progress = $progressPercentage . '% complete';

           return $this->successResponse($task,'Order details updated successfully.', Response::HTTP_OK);
       } catch (\Exception $exception){
           return $this->errorResponse('Something went wrong',$exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
       }
    }
    public function switchProfile()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid token or user not found.',
                ], 401);
            }


            if ($user->role === 'performer') {
                $user->role = 'brand';
            } elseif ($user->role === 'brand') {
                $user->role = 'performer';
            } else {
                return $this->errorResponse('Switching is not for this user.',null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $user->save();

            $newToken = JWTAuth::fromUser($user, ['role' => $user->role]);

            return $this->successResponse([
                'new_role' => $user->role,
                'token'   => $newToken,
            ],'Profile switched successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function allSocialMedia()
    {
        try {
            $user=JWTAuth::user();
            $socialMedia=SocialAccount::with('social:id,name,icon_url')
                        ->where('user_id',$user->id)
                        ->select('id','user_id','sm_id','profile_name','verification_by','verified_at','rejection_reason')
                        ->get();
            if ($socialMedia->isEmpty()) {
                return $this->errorResponse('Social media not found.',null, Response::HTTP_NOT_FOUND);
            }

            return $this->successResponse($socialMedia,'Social media list successfully.', Response::HTTP_OK);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->errorResponse('Token expired.', $e->getMessage(),Response::HTTP_UNAUTHORIZED);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->errorResponse('Token is invalid.',$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function verifiedRequest(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'profile_name'  => 'required|string|max:255',
                'note'          => 'nullable|string|max:500',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation Error. ',$validator->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = JWTAuth::parseToken()->authenticate();

            $socialAccount = SocialAccount::where('user_id', $user->id)
                ->where('sm_id', $id)
                ->first();

            if (!$socialAccount) {
                return $this->errorResponse('Social account not found.', null,Response::HTTP_NOT_FOUND);
            }

            $socialAccount->profile_name = $request->profile_name;
            $socialAccount->note = $request->note;
            $socialAccount->status ='pending';


            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $extension = $file->getClientOriginalExtension();
                $fileName = Str::slug($socialAccount->profile_name) . '-' . time() . '.' . $extension;

                if ($socialAccount->profile_image && Storage::disk('public')->exists($socialAccount->profile_image)) {
                    Storage::disk('public')->delete($socialAccount->profile_image);
                }

                $filePath = $file->storeAs('social_profiles', $fileName, 'public');
                $socialAccount->profile_image = $filePath;
            }

            $socialAccount->save();

            return $this->successResponse($socialAccount,'Social account updated successfully.', Response::HTTP_OK);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->errorResponse('Token expired.',$e->getMessage(), Response::HTTP_UNAUTHORIZED);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->errorResponse('Token is invalid.',$e->getMessage(), Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getReferrer($referral_code)
    {
        $user = User::where('referral_code', $referral_code)->first();

        if (!$user) {
            return $this->errorResponse('Referral code not found.',null, Response::HTTP_NOT_FOUND);
        }

        $signIn = route('auth.signup'.$referral_code);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'referral_code' => $user->referral_code,
            'sign_in' => $signIn,
        ],'Referral Code Found', Response::HTTP_OK);
    }

}

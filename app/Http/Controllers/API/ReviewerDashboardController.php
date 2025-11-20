<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SocialAccount;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ReviewerDashboardController extends Controller
{
    public function allVerificationRequest(){
        return $sc=SocialAccount::with(['user:id,name,avatar',
    'social:id,name,icon_url'])->where('status','pending')->paginate(10,['id', 'user_id', 'sm_id', 'profile_name','note', 'profile_image', 'status']);
    }

    public function viewSocialAccountVerify($id){
        return $sc=SocialAccount::with(['user:id,name,avatar,withdrawal_status',
    'social:id,name,icon_url'])->where(['id'=>$id,'status'=>'pending'])->paginate(10,['id', 'user_id', 'sm_id', 'profile_name','note', 'profile_image', 'status']);
    }

    public function VerifySocialAccount(Request $request)
    {
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:social_accounts,id',
                'status' => 'required|in:pending,verified',
                'withdrawal' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Authenticated reviewer
            $verifyBy = JWTAuth::parseToken()->authenticate();

            // Fetch social account
            $sa = SocialAccount::findOrFail($request->id);
            $sa->status = $request->status;
            $sa->verification_by = $verifyBy->id;
            $sa->verified_at = Now();
            $sa->save();

            // Update related user
            $user = User::findOrFail($sa->user_id);
            $user->withdrawal_status = $request->withdrawal;
            $user->verification_by = $verifyBy->id;
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Social account verified successfully.',
                'data' => [
                    'social_account' => $sa,
                    'user' => $user,
                ],
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token error.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.',
                'error' => $e->getMessage(),
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function rejectSocialAccount(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'id'                => 'required|exists:social_accounts,id',
                'status'            => 'required|in:rejected',
                'withdrawal'        => 'required|in:0,1',
                'rejection_reason'  => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Authenticated reviewer
            $verifyBy = JWTAuth::parseToken()->authenticate();

            // Fetch the social account
            $sa = SocialAccount::findOrFail($request->id);
            $sa->status = $request->status;
            $sa->verification_by = $verifyBy->id;
            $sa->verified_at = now();
            $sa->rejection_reason = $request->rejection_reason;
            $sa->save();

            // Update related user
            $user = User::findOrFail($sa->user_id);
            $user->verification_by = Null;
            $user->withdrawal_status = $request->withdrawal; // assuming rejected accounts can’t withdraw
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Social account rejected successfully.',
                'data' => [
                    'social_account' => $sa,
                    'user' => $user,
                ],
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token error.',
                'error' => $e->getMessage(),
            ], 401);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.',
                'error' => $e->getMessage(),
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




}

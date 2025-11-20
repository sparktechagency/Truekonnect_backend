<?php

namespace App\Http\Controllers\API;

use App\Models\SocialMedia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SocialAccount;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AppController extends Controller
{
    public function updateProfile(Request $request){
        $validator = Validator::make($request->all(), [
            'name'    => 'nullable|string|max:255',
            'avatar'  => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480', // 20 MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user =  JWTAuth::user();

        if ($request->has('name') && $request->name !==NULL) {
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

        // Optionally, return full URL for avatar
        // $user->avatar_url = $user->avatar ? asset('storage/' . $user->avatar) : asset('storage/avatars/default_avatar.png');

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully.',
            'user'    => $user,
        ], 200);
    }
    public function switchProfile(){
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid token or user not found.',
                ], 401);
            }

            // Toggle role
            if ($user->role === 'performer') {
                $user->role = 'brand';
            } elseif ($user->role === 'brand') {
                $user->role = 'performer';
            } else {
                return response()->json([
                    'status'  => false,
                    'message' => 'Switching role not supported for this user type.',
                ], 400);
            }

            // Save the updated role
            $user->save();

            // Generate new JWT with updated role
            $newToken = JWTAuth::fromUser($user, ['role' => $user->role]);

            return response()->json([
                'status'  => true,
                'message' => 'Profile switched successfully.',
                'new_role' => $user->role,
                'token'   => $newToken,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error switching profile.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function allSocialMedia(){
        try {
            $user=JWTAuth::user();
            $socialMedia=SocialAccount::with('social:id,name,icon_url')
                        ->where('user_id',$user->id)
                        ->select('id','user_id','sm_id','profile_name','verification_by','verified_at','rejection_reason')
                        ->get();
            if ($socialMedia->isEmpty()) {
                return response()->json([
                    'status'  => true,
                    'message' => 'No social media accounts found for this user.',
                    'data'    => [],
                ], 200);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Social media accounts retrieved successfully.',
                'data'    => $socialMedia,
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Token has expired.',
                'error'   => $e->getMessage(),
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid token provided.',
                'error'   => $e->getMessage(),
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch social media accounts.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function verifiedRequest(Request $request, $id)
{
    try {
        // Validate input
        $validator = Validator::make($request->all(), [
            'profile_name'  => 'required|string|max:255',
            'note'          => 'nullable|string|max:500',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480', // optional, not forced
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

        // Find the social account (just one, not a collection)
        $socialAccount = SocialAccount::where('user_id', $user->id)
            ->where('sm_id', $id)
            ->first();

        if (!$socialAccount) {
            return response()->json([
                'status'  => false,
                'message' => 'Social account not found.',
            ], 404);
        }

        // Update basic info
        $socialAccount->profile_name = $request->profile_name;
        $socialAccount->note = $request->note;
        $socialAccount->status ='pending';
       

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::slug($socialAccount->profile_name) . '-' . time() . '.' . $extension;

            // Delete old image if exists
            if ($socialAccount->profile_image && Storage::disk('public')->exists($socialAccount->profile_image)) {
                Storage::disk('public')->delete($socialAccount->profile_image);
            }

            $filePath = $file->storeAs('social_profiles', $fileName, 'public');
            $socialAccount->profile_image = $filePath;
        }

        $socialAccount->save();

        return response()->json([
            'status'  => true,
            'message' => 'Social account verified successfully.',
            'data'    => $socialAccount,
        ], 200);

    } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Token expired.',
            'error'   => $e->getMessage(),
        ], 401);

    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Invalid token.',
            'error'   => $e->getMessage(),
        ], 401);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Failed to verify social account.',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

}

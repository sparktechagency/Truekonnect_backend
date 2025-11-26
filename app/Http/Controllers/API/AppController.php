<?php

namespace App\Http\Controllers\API;

use App\Models\SocialMedia;
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
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
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

            // Optionally, return full URL for avatar
            // $user->avatar_url = $user->avatar ? asset('storage/' . $user->avatar) : asset('storage/avatars/default_avatar.png');

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully.',
                'user' => $user,
            ], 200);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return response()->json([
                    'status'  => false,
                    'message' => 'Switching role not supported for this user type.',
                ], 400);
            }

            $user->save();

            $newToken = JWTAuth::fromUser($user, ['role' => $user->role]);

            return $this->successResponse([
                'new_role' => $user->role,
                'token'   => $newToken,
            ],'Profile switched successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return $this->errorResponse('Social media not found.', Response::HTTP_NOT_FOUND);
            }

            return $this->successResponse($socialMedia,'Social media list successfully.', Response::HTTP_OK);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->errorResponse('Token expired.', Response::HTTP_UNAUTHORIZED);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->errorResponse('Token is invalid.', Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();

            $socialAccount = SocialAccount::where('user_id', $user->id)
                ->where('sm_id', $id)
                ->first();

            if (!$socialAccount) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Social account not found.',
                ], 404);
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
            return $this->errorResponse('Token expired.', Response::HTTP_UNAUTHORIZED);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->errorResponse('Token is invalid.', Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

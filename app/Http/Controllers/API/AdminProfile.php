<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use App\Models\TermCondition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminProfile extends Controller
{
    public function myProfile(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'sometimes',
                'phone' => 'sometimes',
                'avatar' => 'sometimes|mimes:jpeg,jpg,png,webp|max:20480',
            ]);

            $user = JWTAuth::parseToken()->authenticate();

            if ($request->hasFile('avatar')) {
                if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }

                $file = $request->file('avatar');
                $extension = $file->getClientOriginalExtension();
                $fileName = Str::slug($request->name ?? $user->avatar) . '.' . $extension;
                $iconPath = $file->storeAs('avatars', $fileName, 'public');
                $data['avatar'] = $iconPath;
            }

            $user->update($data);
            return $this->successResponse($user, 'Profile updated successfully.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function privacyPolicy(Request $request)
    {
        try {
            $data = $request->validate([
                'policy' => 'required',
            ]);

            $privacy = PrivacyPolicy::updateOrCreate(['id'=>1],$data);

            return $this->successResponse($privacy, 'Privacy policy updated successfully.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function privacyRetrive(Request $request){
        try {
            $privacy = PrivacyPolicy::all();

            return $this->successResponse($privacy, 'Privacy policy retrieved.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function privacyPolicyUpdate(Request $request)
    {
        try {
            $data = $request->validate([
                'policy' => 'required',
            ]);

            $privacy = PrivacyPolicy::first();

            if (!$privacy) {
                $privacy = PrivacyPolicy::create($data);
            } else {
                $privacy->update($data);
            }

            return $this->successResponse($privacy, 'Privacy policy updated successfully.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function termCondition(Request $request)
    {
        try {
            $data = $request->validate([
                'terms_conditions' => 'required',
            ]);

            $privacy = TermCondition::updateOrCreate(['id'=>1],$data);

            return $this->successResponse($privacy, 'Terms and Condition updated successfully.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function termsRetrive(Request $request){
        try {
            $privacy = TermCondition::all();

            return $this->successResponse($privacy, 'Terms and Condition retrieved.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Soething went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function termConditionUpdate(Request $request)
    {
        try {
            $data = $request->validate([
                'terms_conditions' => 'required',
            ]);

            $privacy = TermCondition::first();

            if (!$privacy) {
                $privacy = TermCondition::create($data);
            } else {
                $privacy->update($data);
            }

            return $this->successResponse($privacy, 'Terms and Condition updated successfully.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function adminList()
    {
        try {
            $admin = User::where('role', 'admin')->latest()->paginate(10);

            return $this->successResponse($admin, 'Admin list.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addAdmin(Request $request){
        try {
           $validator = Validator::make($request->all(), [
                'name'     => 'required|string',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    $validator->errors()->first(),
                    $validator->errors()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $data = $validator->validated();
            $data['password'] = Hash::make($data['password']);
            $data['role'] = 'admin';

            $otp = rand(100000, 999999);
            $otpExpiry = Carbon::now()->addMinutes(10);

            $data['otp'] = $otp;
            $data['otp_expires_at'] = $otpExpiry;


            $user = User::create($data);

            $user->otpVerifyLink = route('admin.otp.verify');
            Mail::raw("Your OTP is: $otp. It will expire at " . $otpExpiry->format('H:i:s'), function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('Your OTP Code');
            });

            return $this->successResponse($user, 'Admin added successfully.', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

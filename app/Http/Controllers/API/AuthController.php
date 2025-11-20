<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\SocialMedia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SocialAccount;
use Illuminate\Support\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Mail\ForgotPasswordOtpMail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class AuthController extends Controller
{
    public function signUp(Request $request){
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'phone'           => 'required|string|max:255|unique:users,phone',
            'country_id'      => 'required|exists:countries,id',
            'referral_code'   => 'nullable|string|max:255|exists:users,referral_code',
            'role'            => 'required|in:performer,brand',
            'password'        => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'error_type' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422);
        }

        do {
            $referralCode = strtoupper(substr($request->name, 0, 3)) . random_int(100000, 999999);
        } while (User::where('referral_code', $referralCode)->exists());

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'country_id' => $request->country_id,
            'role'       => $request->role,
            'referral_code' => $referralCode,
            'password'   => Hash::make($request->password),
            'avatar'   => 'avatars/default_avatar.png',
        ]);
        $socialMedia=SocialMedia::get();
        foreach($socialMedia as $item){
            SocialAccount::create([
                'user_id'=>$user->id,
                'sm_id'=>$item->id,
            ]);
        }
        if ($request->filled('referral_code')) {
            $refUser = User::where('referral_code', $request->referral_code)->first();
            if ($refUser) {
                $user->referral_id = $refUser->id;
                $user->save();
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'User successfully registered',
            'data'    => $user,
        ], 201);
    }
    public function signIn(Request $request){
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email|max:255|exists:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'error_type' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid your password',
            ], 401);
        }

        $user = JWTAuth::user();

        return response()->json([
            'status'  => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user, 
            // [
            //     'id'           => $user->id,
            //     'name'         => $user->name,
            //     'email'        => $user->email,
            //     'phone'        => $user->phone,
            //     'active_role'  => $user->role,
            //     'country_id'   => $user->country_id,
            // ],
        ], 200);
    }
    public function forgotPassword(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'error_type' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Generate OTP (use random_int for more secure OTP)
        $otp = random_int(100000, 999999);

        // Save OTP and expiry
        $user->otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        try {
            // Send OTP email
            Mail::to($user->email)->send(new ForgotPasswordOtpMail($user, $otp));

            return response()->json([
                'status'  => true,
                'message' => 'OTP sent to your email',
                'email'   => $user->email,
            ], 200);

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Forgot Password Email Error: '.$e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to send OTP email. Please try again later.',
                'error'=> $e->getMessage(),
            ], 500);
        }
    }
    public function otpVerify(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|exists:users,email',
            'otp'   => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'error_type' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>=', now()) // be consistent with your column name
            ->first();
        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'OTP is incorrect or has expired.',
            ], 400);
        }
            $user->otp = null;
            $user->otp_expires_at = null;
            $user->save();
            return response()->json([
                'status'  => true,
                'message' => 'OTP verified successfully. Now reset password.',
                'data'    => [
                    'email' => $request->email,
                    'user_id' => $user->id,
                ],
            ], 200);

    }
    public function setNewPassword(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'user_email'    => 'required|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'error_type' => 'Validation errors',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('id',$request->user_id)->first();

        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => 'No user found.',
            ], 404);
        }
        $user->password = Hash::make($request->password);
        $user->save();

        $token = JWTAuth::fromUser($user);
        return response()->json([
            'status'  => true,
            'message' => 'Password reset successful. You are now logged in.',
            'token'   => $token,
            'token_type'  => 'Bearer',
            'user'    => $user,
        ], 200);
    }
    public function changePassword(Request $request){
        try {
            $request->validate([
                'current_password' => 'required|string|min:8',
                'password'         => 'required|string|min:8|confirmed',
            ]);

            // Get authenticated user from JWT
            $user =  JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'User not found or token invalid.',
                ], 401);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Current password is incorrect.',
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'status'  => true,
                'message' => 'Password updated successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error while changing password.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function refreshToken(Request $request){
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authorization token not found.'
                ], 400);
            }

            // Refresh the token
            $newToken = JWTAuth::refresh($token);

            return response()->json([
                'status' => true,
                'message' => 'Token refreshed successfully.',
                'token' => $newToken
            ]);
        } 
        catch (TokenBlacklistedException $e) {
            return response()->json([
                'status' => false,
                'message' => 'This token has already been used or blacklisted. Please log in again.'
            ], 401);
        } 
        catch (TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token expired. Please log in again.'
            ], 401);
        } 
        catch (TokenInvalidException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid token.'
            ], 401);
        } 
        catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token refresh failed: ' . $e->getMessage()
            ], 500);
        }
    }
    public function signOut(Request $request){
        try {
            JWTAuth::invalidate(JWTAuth::parseToken());

            return response()->json([
                'status' => true,
                'message' => 'Successfully signed out.'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to sign out, maybe token already invalid or missing.'
            ], 401);
        }
    }
}

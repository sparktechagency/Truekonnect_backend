<?php

namespace App\Http\Controllers\API;

use App\Models\Countrie;
use App\Models\Payment;
use App\Models\User;
use App\Models\SocialMedia;
use App\Models\Withdrawal;
use App\Services\KorbaXchangeService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function signUp(Request $request, KorbaXchangeService $korba){
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'phone' => 'required|string|max:255|unique:users,phone',
                'country_id' => 'required|exists:countries,id',
                'referral_code' => 'nullable|string|max:255|exists:users,referral_code',
                'role' => 'required|in:performer,brand',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation Error '.$validator->errors(), Response::HTTP_BAD_REQUEST);
            }

            do {
                $referralCode = strtoupper(substr($request->name, 0, 3)) . random_int(100, 999);
            } while (User::where('referral_code', $referralCode)->exists());

            $otp = rand(100000, 999999);
            $otpExpiry = Carbon::now()->addMinutes(10);

            if (in_array($request->role, ['performer', 'brand'])) {
                $country = Countrie::where('id', $request->country_id);
                $payload = [
                    'client_id' => env('KORBA_CLIENT_ID'),
                    'phone_number' => $country->dial_code . $request->phone,
                    'code' => $otp,
                    'platform' => env('APP_NAME') . '. OTP Expire at ' . $otpExpiry,
                ];
                $otpSend = $korba->ussdOTP($payload);
            } else {
                Mail::raw("Your OTP is: $otp. It will expire at " . $otpExpiry->format('H:i:s'), function ($message) use ($request) {
                    $message->to($request->email)
                        ->subject('Your OTP Code');
                });
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'country_id' => $request->country_id,
                'role' => $request->role,
                'otp' => $otp,
                'otp_expires_at' => $otpExpiry,
                'referral_code' => $referralCode,
                'password' => Hash::make($request->password),
                'avatar' => 'avatars/default_avatar.png',
            ]);

            $socialMedia = SocialMedia::get();
            foreach ($socialMedia as $item) {
                SocialAccount::create([
                    'user_id' => $user->id,
                    'sm_id' => $item->id,
                ]);
            }
            if ($request->filled('referral_code')) {
                $refUser = User::where('referral_code', $request->referral_code)->first();
                if ($refUser) {
                    $user->referral_id = $refUser->id;
                    $user->save();
                }
            }
            $referralUrl = url('/ref/' . $user->referral_code);
            DB::commit();

            return $this->successResponse([
                'user' => $user,
                'referralUrl' => $referralUrl,
                'otp send' => $otpSend,
            ], 'User Successfully Register', Response::HTTP_CREATED);
        }
        catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function signIn(Request $request){
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation errors'.$validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $login = $request->login;
        $password = $request->password;

        $userQuery = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (!$userQuery) {
            return $this->errorResponse('User not found.', Response::HTTP_NOT_FOUND);
        }

        if (in_array($userQuery->role, ['performer', 'brand'])) {
            if (!$userQuery->phone_verified_at) {
                return $this->errorResponse('Phone number not verified.', Response::HTTP_UNAUTHORIZED);
            }
            $loginField = 'phone';
        } else {
            if (!$userQuery->email_verified_at) {
                return $this->errorResponse('Email not verified.', Response::HTTP_UNAUTHORIZED);
            }
            $loginField = 'email';
        }

        $credentials = [
            $loginField => $login,
            'password'  => $password,
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }


        $user = JWTAuth::user();

        return $this->successResponse(['token' => $token,'user' => $user], 'User Successfully Login', Response::HTTP_OK);
    }

    public function myProfile()
    {
        try {
            $user =JWTAuth::parseToken()->authenticate();

            $referralCode = url('/ref/' . $user->referral_code);

            $payment = Payment::with('user:id,name,email,avatar')
                ->whereHas('user', fn($q) => $q->where('referral_id', Auth::id()))
                ->where('status', 'completed')
                ->orderBy('created_at') // earliest first
                ->get()
                ->unique('user_id');

            $referralsWithdrawals = Withdrawal::with('user:id,name,email,avatar')
                ->whereHas('user', fn($q) => $q->where('referral_id', Auth::id()))
                ->where('status', 'completed')
                ->orderBy('created_at')
                ->get()
                ->unique('user_id');


            return $this->successResponse(['user'=>$user->only(['avatar', 'name', 'email', 'phone','referral_code']),'referral_link'=>$referralCode,'creator'=>$payment,'performer'=>$referralsWithdrawals], 'User Successfully Login', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function forgetPasswordOTPSend(Request $request, KorbaXchangeService $korba)
    {
        DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation errors'.$validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $login = $request->login;

        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (!$user) {
            return $this->errorResponse('User not found.', Response::HTTP_NOT_FOUND);
        }

        $otp = random_int(100000, 999999);
        $otpExpiry = now()->addMinutes(10);

        $user->otp = $otp;
        $user->otp_expires_at = $otpExpiry;
        $user->save();

        try {
            if (in_array($user->role, ['performer', 'brand'])) {
                $country = Countrie::find($user->country_id);
                if (!$country) {
                    return $this->errorResponse('Country not found.', Response::HTTP_NOT_FOUND);
                }

                $payload = [
                    'client_id'    => env('KORBA_CLIENT_ID'),
                    'phone_number' => $country->dial_code . $user->phone,
                    'code'         => $otp,
                    'platform'     => env('APP_NAME') . ' OTP Expire at ' . $otpExpiry,
                ];

              $otpSend =  $korba->ussdOTP($payload);

            } else {
                Mail::raw("Your OTP is: $otp. It will expire at " . $otpExpiry->format('H:i:s'), function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Your OTP Code');
                });
            }
            DB::commit();

            return $this->successResponse([
                'otp'=>$otp,'otp send'=>$otpSend,'user'=>$login
            ], 'OTP Send Successfully', Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function forgetOTPverify(Request $request, KorbaXchangeService $korba)
    {
        try {
            $data = $request->validate([
                'login' => 'required|string|max:255',
                'otp' => 'required|string|max:255',
            ]);


            $user = User::where('phone', $data['login'])->orWhere('email', $data['login'])
                ->where('otp',$data['otp'])
                ->where('otp_expires_at','>',now())
                ->first();

            return $this->successResponse(['user'=>$user], 'OTP Verify Successfully', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function forgotPassword(Request $request, KorbaXchangeService $korba)
    {
        try {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|max:255',
            'otp' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation errors'.$validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $login = $request->login;

        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (!$user) {
            return $this->errorResponse('User not found.', Response::HTTP_NOT_FOUND);
        }

        $user->password = Hash::make($request->password);
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return $this->successResponse($user, 'User Successfully Forgot Password', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function otpPhoneVerify(Request $request){
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:255|exists:users,phone',
            'otp'   => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation errors'.$validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $user = User::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>=', now())
            ->first();

        if (!$user) {
            return $this->errorResponse('OTP is incorrect or has expired.', Response::HTTP_BAD_REQUEST);
        }
        $user->phone_verified_at = Carbon::now();
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return $this->successResponse(['phone'=>$request->phone, 'user'=>$user->id], 'User Successfully Verify OTP', Response::HTTP_OK);
    }

    public function resendPhoneOTP(Request $request, KorbaXchangeService $korba)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string|max:255|exists:users,phone',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Something went wrong. ' . $validator->errors(), 400);
            }
            $otp = rand(100000, 999999);
            $otpExpiry = Carbon::now()->addMinutes(10);

            $payload = [
                'client_id' => env('KORBA_CLIENT_ID'),
                'phone_number' => '+233' . $request->phone,
                'code' => $otp,
                'platform' => env('APP_NAME') . 'OTP Expire at ' . $otpExpiry,
            ];
            $otpSend = $korba->ussdOTP($payload);

            $user = User::where('phone', $request->phone)->whereIn('role', ['brand', 'performer'])->first();

            if (!$user) {
                return $this->errorResponse('Phone number not found', Response::HTTP_NOT_FOUND);
            }

            $user->otp = $otp;
            $user->otp_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            DB::commit();

            return $this->successResponse([
                'user' => $user,
                'otp' => $otpSend,
            ], 'OTP sent to your phone', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendEmailOTP(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|max:255|exists:users,email',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Something went wrong. ' . $validator->errors(), 400);
            }
            $otp = rand(100000, 999999);
            $otpExpiry = Carbon::now()->addMinutes(10);

            $user = User::where('email', $request->email)->whereIn('role', ['admin', 'reviewer'])->first();

            if (!$user) {
                return $this->errorResponse('Email not found', Response::HTTP_NOT_FOUND);
            }

            $user->otp = $otp;
            $user->otp_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            Mail::raw("Your OTP is: $otp. It will expire at " . $otpExpiry->format('H:i:s'), function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your OTP Code');
            });

            DB::commit();

            return $this->successResponse($user, 'OTP sent to your email', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function otpVerify(Request $request){
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255|exists:users,email',
                'otp' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Something went wrong. ' . $validator->errors(), 400);
            }

            $user = User::where('email', $request->email)
                ->where('otp', $request->otp)
                ->where('otp_expires_at', '>=', now()) // be consistent with your column name
                ->first();
            if (!$user) {
                return $this->errorResponse('OTP is incorrect or has expired.', Response::HTTP_BAD_REQUEST);
            }
            $user->email_verified_at = Carbon::now();
            $user->otp = null;
            $user->otp_expires_at = null;
            $user->save();

            DB::commit();
            return $this->successResponse(['email' => $request->email, 'user' => $user->id], 'User Successfully Verify OTP', Response::HTTP_OK);
        }
        catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    public function setNewPassword(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
//            'user_email'    => 'required|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation errors. ' .$validator->errors(), 400);
        }

        $user = User::where('id',$request->user_id)->first();

        if (! $user) {
            return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
        }
        $user->password = Hash::make($request->password);
        $user->save();

        $token = JWTAuth::fromUser($user);

        return $this->successResponse(['token' => $token,'user'=>$user], 'Password set successfully', Response::HTTP_OK);
    }

    public function changePassword(Request $request){
        try {
            $request->validate([
                'current_password' => 'required|string|min:8',
                'password'         => 'required|string|min:8|confirmed',
            ]);

            $user =  JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'User not found or token invalid.',
                ], 401);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Current password is incorrect.',
                ], 400);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            JWTAuth::invalidate(JWTAuth::getToken());

            $newToken = JWTAuth::fromUser($user);

            return $this->successResponse([
//                'user' => $user,
                'new_token' => $newToken,
            ],'Password reset successful', Response::HTTP_OK);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed'.$e->getErrors(), Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function refreshToken(Request $request){
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return $this->errorResponse('Token not provided', Response::HTTP_UNAUTHORIZED);
            }

            $newToken = JWTAuth::refresh($token);

            return $this->successResponse($newToken, 'Token refreshed successfully', Response::HTTP_OK);
        }
        catch (TokenBlacklistedException $e) {
            return $this->errorResponse('Token blacklisted. '.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (TokenExpiredException $e) {
            return $this->errorResponse('Token expired. '.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (TokenInvalidException $e) {
            return $this->errorResponse('Token invalid. '.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (JWTException $e) {
            return $this->errorResponse('Token Exception: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function signOut(Request $request){
        try {
            JWTAuth::invalidate(JWTAuth::parseToken());

            return $this->successResponse(null, 'Successfully signed out.', Response::HTTP_OK);
        } catch (JWTException $e) {
            return $this->errorResponse('Failed to sign out. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

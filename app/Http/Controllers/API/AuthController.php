<?php

namespace App\Http\Controllers\API;

use App\Models\Countrie;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskPerformer;
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
                'country_id' => 'required|exists:countries,dial_code',
                'referral_code' => 'nullable|string|max:255|exists:users,referral_code',
                'role' => 'required|in:performer,brand',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation Error '.$validator->errors()->first(),$validator->errors()->first(), Response::HTTP_BAD_REQUEST);
            }

            do {
                $referralCode = strtoupper(substr($request->name, 0, 3)) . random_int(100, 999);
            } while (User::where('referral_code', $referralCode)->exists());

            $otp = rand(100000, 999999);
            $otpExpiry = Carbon::now()->addMinutes(10);

            if (in_array($request->role, ['performer', 'brand'])) {

                $payload = [
                    'client_id' => env('KORBA_CLIENT_ID'),
                    'phone_number' => $request->country_id . $request->phone,
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

            $country = Countrie::where('dial_code', $request->country_id)->first();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'country_id' => $country->id,
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
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function signIn(Request $request){
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'type' => 'required|string|in:email,phone',
            'role' => 'nullable|string|in:performer,brand,reviewer,admin'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $login = $request->email;
        $password = $request->password;

        $role = $request->role ?? null;

        $userQuery = User::where($request->type, $login)->first();

        if ($role && $userQuery->role !== $role) {
            return $this->errorResponse(
                null,"You registered as '{$userQuery->role}'. You cannot sign in as '$role'.",
                403
            );
        }
        if (!$userQuery) {
            return $this->errorResponse('User not found.','User not found.', Response::HTTP_NOT_FOUND);
        }

        if ($userQuery->status === 'banned') {
            return $this->errorResponse(
                null,
                'Your account is banned. Please contact support.',
                Response::HTTP_FORBIDDEN
            );
        }

        if (in_array($userQuery->role, ['performer', 'brand'])) {
            if (!$userQuery->phone_verified_at) {
                return $this->errorResponse('Phone number not verified.',null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $loginField = 'phone';
        } else {
            if (!$userQuery->email_verified_at) {
                return $this->errorResponse('Email not verified.',null, Response::HTTP_UNAUTHORIZED);
            }
            $loginField = 'email';
        }

        $credentials = [
            $loginField => $login,
            'password'  => $password,
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->errorResponse('Invalid credentials.',null, Response::HTTP_UNAUTHORIZED);
        }

        $user = JWTAuth::user();

        return $this->successResponse(['token' => $token,'user' => $user], 'User Successfully Login', Response::HTTP_OK);
    }

    public function myProfile()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $userDetails = User::with('country')->where('id', $user->id)->first();
            $referralCode = url('/ref/' . $user->referral_code);
//            $response = null;
//            if (Auth::user()->role == 'reviewer'){
//                $totalPendingAccount = User::where('status','pending')->count();
//                $totalPendingTask = Task::where('status','pending')->count();
//                $totalPendingdOrder = TaskPerformer::where('status','pending')->count();
//
//                $response = [
//                    'total_pending_accounts' => $totalPendingAccount,
//                    'total_pending_task' => $totalPendingTask,
//                    'total_pending_order' => $totalPendingdOrder,
//                ];
//            }
            $payment = User::where('referral_id',Auth::id())->where('role','brand')->get(['id','name','avatar']);
//                ->unique('user_id');

            $referralsWithdrawals = User::where('referral_id',Auth::id())->where('role','performer')->get(['id','name','avatar']);
//                ->unique('user_id');

            $total = User::where('referral_id',Auth::id())->where('role','brand')->count() + User::where('referral_id',Auth::id())->where('role','performer')->count();

            $userDetails->total_ref = $total;
            return $this->successResponse(['user'=>$userDetails,'referral_link'=>$referralCode,'creator'=>$payment,'performer'=>$referralsWithdrawals], 'User Successfully Login', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function forgetPasswordOTPSend(Request $request, KorbaXchangeService $korba)
    {
        DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $login = $request->login;

        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (!$user) {
            return $this->errorResponse('User not found',null, Response::HTTP_NOT_FOUND);
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
                    return $this->errorResponse('Country not found.',null, Response::HTTP_NOT_FOUND);
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
                'otp'=>$otp,'otp send'=>$otpSend ?? null,'user'=>$login
            ], 'OTP Send Successfully', Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
            return $this->errorResponse('Something went wrong',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
            return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(),Response::HTTP_BAD_REQUEST);
        }

        $login = $request->login;

        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->where('otp_expires_at', '>', now())
            ->first();

        if (!$user) {
            return $this->errorResponse(null,'User not found.', Response::HTTP_NOT_FOUND);
        }

        $user->password = Hash::make($request->password);
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return $this->successResponse($user, 'User Successfully Forgot Password', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(),'Something went wrong.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function otpPhoneVerify(Request $request){
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:255|exists:users,phone',
            'otp'   => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $user = User::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('otp_expires_at', '>=', now())
            ->first();

        if (!$user) {
            return $this->errorResponse(null,'OTP is incorrect or has expired.', Response::HTTP_BAD_REQUEST);
        }
        $user->phone_verified_at = Carbon::now();
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        $token = JWTAuth::fromUser($user);

        return $this->successResponse(['user'=>$user, 'user_id'=>$user->id, 'token' => $token], 'User Successfully Verify OTP', Response::HTTP_OK);
    }

    public function resendPhoneOTP(Request $request, KorbaXchangeService $korba)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string|max:255|exists:users,phone',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(),'Something went wrong. '.$validator->errors()->first() , 400);
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
                return $this->errorResponse(null,'Phone number not found', Response::HTTP_NOT_FOUND);
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
            return $this->errorResponse($e->getMessage(),'Something went wrong.', Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return $this->errorResponse($validator->errors()->first(),'Something went wrong. '.$validator->errors()->first(), 400);
            }
            $otp = rand(100000, 999999);
            $otpExpiry = Carbon::now()->addMinutes(10);

            $user = User::where('email', $request->email)->whereIn('role', ['admin', 'reviewer'])->first();

            if (!$user) {
                return $this->errorResponse(null,'Email not found', Response::HTTP_NOT_FOUND);
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
            return $this->errorResponse($e->getMessage(),'Something went wrong.', Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return $this->errorResponse($validator->errors()->first(),'Something went wrong. ' .$validator->errors()->first(), 400);
            }

            $user = User::where('email', $request->email)
                ->where('otp', $request->otp)
                ->where('otp_expires_at', '>=', now()) // be consistent with your column name
                ->first();
            if (!$user) {
                return $this->errorResponse(null,'OTP is incorrect or has expired.', Response::HTTP_BAD_REQUEST);
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
            return $this->errorResponse($e->getMessage(),'Something went wrong.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    public function setNewPassword(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
//            'user_email'    => 'required|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(), 400);
        }

        $user = User::where('id',$request->user_id)->first();

        if (! $user) {
            return $this->errorResponse(null,'User not found', Response::HTTP_NOT_FOUND);
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
            return $this->errorResponse($e->getErrors(),'Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(),'Something went wrong.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function refreshToken(Request $request){
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return $this->errorResponse(null,'Token not provided', Response::HTTP_UNAUTHORIZED);
            }

            $newToken = JWTAuth::refresh($token);

            return $this->successResponse($newToken, 'Token refreshed successfully', Response::HTTP_OK);
        }
        catch (TokenBlacklistedException $e) {
            return $this->errorResponse('Token blacklisted. ',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (TokenExpiredException $e) {
            return $this->errorResponse('Token expired. ',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (TokenInvalidException $e) {
            return $this->errorResponse('Token invalid. ',$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }
        catch (JWTException $e) {
            return $this->errorResponse('Token Exception: ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function profile()
    {
        try {
            $user = Auth::user();

            $response = null;

                $totalPendingAccount = User::where('status','pending')->count();
                $totalPendingTask = Task::where('status','pending')->count();
                $totalPendingdOrder = TaskPerformer::where('status','pending')->count();
            if (Auth::user()->role == 'reviewer'){
                $user->total_pending_accounts = $totalPendingAccount;
                $user->total_pending_task = $totalPendingTask;
                $user->total_pending_order = $totalPendingdOrder;
            }

//            $user->avatar = asset('storage/'.$user->avatar);

            return $this->successResponse($user, 'Profile retrive successfully', Response::HTTP_OK);


        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function profileUpdate(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'sometimes|string',
                'email' => 'sometimes|string|email|unique:users,email',
                'phone' => 'sometimes|string',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
            ]);

            $user = Auth::user();

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

            if (($request->filled('email') || $request->filled('phone'))
                && in_array($user->role, ['performer', 'brand'])) {

                return $this->errorResponse(
                    'You can not update your email or phone number',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $user->update([
                'name'=>$data['name'] ?? $user->name,
                'email'=>$data['email'] ?? $user->email,
                'phone'=>$data['phone'] ?? $user->phone,
                'avatar'=>$data['avatar'] ?? $user->avatar,
            ]);

            return $this->successResponse($user, 'Profile updated successfully', Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(),$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function signOut(Request $request){
        try {
            JWTAuth::invalidate(JWTAuth::parseToken());

            return $this->successResponse(null, 'Successfully signed out.', Response::HTTP_OK);
        } catch (JWTException $e) {
            return $this->errorResponse('Failed to sign out. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

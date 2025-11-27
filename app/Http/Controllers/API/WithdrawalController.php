<?php

namespace App\Http\Controllers\API;

use App\Models\TaskPerformer;
use App\Notifications\UserNotification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Models\Task;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class WithdrawalController extends Controller
{
    public function myWalletInfo(){
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found.',Response::HTTP_NOT_FOUND);
            }
            $taskCount = TaskPerformer::where('user_id', $user->id)->where('status','completed')->count();
            $info = User::where('id', $user->id)
                ->with('country:id,name,token_rate,currency_code')
                ->first(['id','country_id','balance','earn_token','convert_token','withdrawal_status']);
            if (!$info) {
                return $this->errorResponse('User wallet data not found.',Response::HTTP_CONFLICT);
            }
            return $this->successResponse([$info, 'total_task'=>$taskCount],'User wallet successfully fetched.', Response::HTTP_OK);
        } catch (TokenExpiredException $e) {
            return $this->errorResponse('Token has expired. Please log in again.'.$e->getMessage(),Response::HTTP_FORBIDDEN);
        } catch (JWTException $e) {
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return $this->errorResponse('Something went wrong while fetching wallet info.'. $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function dashboardHistory(){
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found.',Response::HTTP_NOT_FOUND);
            }
            $withdrawals = Withdrawal::where('user_id', $user->id)->where('status', 'success')->paginate(10,['id','user_id','amount','status']);
            $totalWithdrawal = $withdrawals->sum('amount');
            $info = User::where('id', $user->id)
                ->with('country:id,name,token_rate,currency_code')
                ->first(['id','name','country_id','balance','earn_token','convert_token','withdrawal_status']);
            if (!$info) {
                return $this->errorResponse('User wallet data not found.',Response::HTTP_CONFLICT);
            }
            return $this->successResponse([
                'name'=>$info->name,
                'total_earn_token'=> $info->earn_token+$info->convert_token,
                'total_withdraw'=>$totalWithdrawal,
                'available_balance'=>$info->balance,
                'withdrawal_history'=>$withdrawals
            ], 'User wallet successfully fetched.', Response::HTTP_OK);
        } catch (TokenExpiredException $e) {
            return $this->errorResponse('Token has expired. Please log in again.'.$e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (JWTException $e) {
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return $this->errorResponse('Something went wrong while fetching wallet info.'.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function tokenConvert(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'token' => 'required|numeric|min:1'
            ]);
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return $this->errorResponse('User not found.',Response::HTTP_NOT_FOUND);
            }
            $info = User::where('id', $user->id)
                ->with('country:id,name,token_rate,currency_code')
                ->first(['id','country_id','balance','earn_token','convert_token','withdrawal_status']);
            if (!$info) {
                return $this->errorResponse('User wallet data not found.',Response::HTTP_CONFLICT);
            }
            if ($info->earn_token < $request->token) {
                return $this->errorResponse('Insufficient earn token to convert.',Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $info->earn_token -= $request->token;
            $info->convert_token += $request->token;
            $info->balance += $request->token * $info->country->token_rate;
            $info->save();

            $title = 'Token has been converted!';
            $body = 'Your new balance is '. $info->balance;

            $info->notify(new UserNotification($title, $body));

            DB::commit();

            return $this->successResponse([
                'balance' => $info->balance,
                'earn_token' => $info->earn_token,
                'convert_token' => $info->convert_token,
                'currency_code' => $info->country->currency_code,
                ], 'Tokens converted successfully.',Response::HTTP_OK);

        } catch (TokenExpiredException $e) {
            DB::rollback();
            return $this->errorResponse('Token has expired. Please log in again.'.$e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (JWTException $e) {
            DB::rollback();
            return $this->errorResponse('Invalid or missing token.'.$e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong while fetching wallet info.'.$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

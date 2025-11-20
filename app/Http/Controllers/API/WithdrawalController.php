<?php

namespace App\Http\Controllers\API;

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
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ], 404);
            }
            $taskCount = Task::where('user_id', $user->id)->count();
            $info = User::where('id', $user->id)
                ->with('country:id,name,token_rate,currency_code')
                ->first(['id','country_id','balance','earn_token','convert_token','withdrawal_status']);
            if (!$info) {
                return response()->json([
                    'status' => false,
                    'message' => 'User wallet data not found.',
                ], 409);
            }
            return response()->json([
                'status' => true,
                'message' => 'User wallet successfully fetched.',
                'data'=> $info,
                'total_task' => $taskCount
            ], 200);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching wallet info.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function dashboardHistory(){
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ], 404);
            }
            $withdrawals = Withdrawal::where('user_id', $user->id)->where('status', 'success')->get(['id','user_id','amount','status']);
            $totalWithdrawal = $withdrawals->sum('amount');
            $info = User::where('id', $user->id)
                ->with('country:id,name,token_rate,currency_code')
                ->first(['id','name','country_id','balance','earn_token','convert_token','withdrawal_status']);
            if (!$info) {
                return response()->json([
                    'status' => false,
                    'message' => 'User wallet data not found.',
                ], 409);
            }
            return response()->json([
                'status' => true,
                'message' => 'User wallet successfully fetched.',
                'data'=> [
                    'name'=>$info->name,
                    'total_earn_token'=> $info->earn_token+$info->convert_token,
                    'total_withdraw'=>$totalWithdrawal,
                    'available_balance'=>$info->balance,
                    'withdrawal_history'=>$withdrawals
                ],
            ], 200);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching wallet info.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function tokenConvert(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|numeric|min:1'
            ]);
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
                ], 404);
            }
            $info = User::where('id', $user->id)
                ->with('country:id,name,token_rate,currency_code')
                ->first(['id','country_id','balance','earn_token','convert_token','withdrawal_status']);
            if (!$info) {
                return response()->json([
                    'status' => false,
                    'message' => 'User wallet data not found.',
                ], 409);
            }
            if ($info->earn_token < $request->token) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient earn tokens to convert.',
                ], 422);
            }
            $info->earn_token -= $request->token;
            $info->convert_token += $request->token;
            $info->balance += $request->token * $info->country->token_rate;
            $info->save();

            return response()->json([
                'status' => true,
                'message' => 'Tokens converted successfully.',
                'data' => [
                    'balance' => $info->balance,
                    'earn_token' => $info->earn_token,
                    'convert_token' => $info->convert_token,
                    'currency_code' => $info->country->currency_code,
                ]
            ], 200);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Token has expired. Please log in again.',
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or missing token.',
            ], 401);

        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while converting tokens.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}

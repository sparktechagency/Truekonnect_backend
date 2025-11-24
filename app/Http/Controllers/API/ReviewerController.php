<?php

namespace App\Http\Controllers\API;

use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use Illuminate\Http\Request;
use App\Mail\AccountbannedMail;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ReviewerController extends Controller
{
     public function addReviewer(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'       => 'required|string|max:255',
                'email'      => 'required|string|email|max:255|unique:users,email',
                'phone'      => 'required|string|max:255|unique:users,phone',
                'country_id' => 'required|exists:countries,id',
                'password'   => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $authUser = JWTAuth::user();

            $user = User::create([
                'name'             => $request->name,
                'email'            => $request->email,
                'phone'            => $request->phone,
                'country_id'       => $request->country_id,
                'role'             => 'reviewer',
                'password'         => Hash::make($request->password),
                'avatar'           => 'avatars/default_avatar.png',
                'verification_by'  => $authUser->id ?? null,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Reviewer successfully registered.',
                'data'    => $user,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while adding the reviewer.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function allReviewer()
    {
        try {
            $reviewers = User::where('role', 'reviewer')->paginate(10);

            if ($reviewers->isEmpty()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'No reviewers found.',
                ], 404);
            }

            return response()->json([
                'status'  => true,
                'message' => 'All reviewers retrieved successfully.',
                'data'    => $reviewers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to retrieve reviewers.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function actionReviewer(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status'  => 'required|in:active,banned',
                'message' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $reviewer = User::where('role', 'reviewer')->findOrFail($id);

            $reviewer->update(['status' => $request->status]);

            Mail::to($reviewer->email)->send(new AccountbannedMail($reviewer, $request->status, $request->message));

            return response()->json([
                'status'  => true,
                'message' => "Reviewer account has been {$request->status}.",
                'data'    => $reviewer,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Reviewer not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to update reviewer status.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function viewReviewer($id)
    {
        try {
            // Find the reviewer by ID and role
            $reviewer = User::where('role', 'reviewer')->findOrFail($id);

            $totalVerified = User::where('verification_by', $id)->count();
            $totalVerifiedTask = Task::where('verified_by', $id)->count();
            $totalVerifiedOrder = TaskPerformer::where('verified_by', $id)->count();

            $totalPendingAccounts = User::whereIn('role',['performer','brand'])->count();
            $totalPendingOrders = TaskPerformer::where('status','pending')->count();
            $totalPendingTask = Task::where('status','pending')->count();

            $totalPending = $totalPendingAccounts + $totalPendingOrders + $totalPendingTask;
            $totalVerifiedAll = $totalVerified + $totalVerifiedTask + $totalVerifiedOrder;
            $overallPerformance = ($totalPending + $totalVerifiedAll) > 0
                ? round(($totalVerifiedAll / ($totalPending + $totalVerifiedAll)) * 100)
                : 0;

            return $this->successResponse([
                'reviewer' => $reviewer,
                'totalVerified' => $totalVerified,
                'totalVerifiedTask' => $totalVerifiedTask,
                'totalVerifiedOrder' => $totalVerifiedOrder,
                'totalPendingAccounts' => $totalPendingAccounts,
                'totalPendingOrders' => $totalPendingOrders,
                'totalPendingTask' => $totalPendingTask,
                'overallPerformance' => $overallPerformance,
            ],'Reviewer successfully viewed!',Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Reviewer not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to retrieve reviewer.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function myProfile()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
            }

            $userDetails = User::select('id', 'name', 'email', 'avatar')
                ->where('id', Auth::id())
                ->first();

            $totalVerified = User::where('verification_by', Auth::id())->count();
            $totalVerifiedTask = Task::where('verified_by', Auth::id())->count();
            $totalVerifiedOrder = TaskPerformer::where('verified_by', Auth::id())->count();

            return $this->successResponse([
                'my_profile'=>  $userDetails,
                'total_verified_accounts' => $totalVerified,
                'total_verified_task' => $totalVerifiedTask,
                'total_verified_order' => $totalVerifiedOrder,
            ], 'User Details Retrieve' ,Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong while retrieving your profile.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateProfile(Request $request){
        try {
            $data = $request->validate([
                'name'       => 'required|string|max:255',
                'avatar'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20480',
            ]);

            $userDetails = Auth::user();

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $extension = $file->getClientOriginalExtension();

                $fileName = uniqid() . '-' . time() . '.' . $extension;

                if ($userDetails->avatar && Storage::disk('public')->exists($userDetails->avatar)) {
                    Storage::disk('public')->delete($userDetails->avatar);
                }

                $filePath = $file->storeAs('avatars', $fileName, 'public');
                $data['avatar'] = $filePath;
            }

            $userDetails->update($data);

            $userDetails->avatar = asset('storage/' . $filePath);

            return $this->successResponse($userDetails, 'Profile Data updated' ,Response::HTTP_OK);
        }catch (\Exception $e) {
            return $this->errorResponse('Something went wrong while retrieving your profile. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

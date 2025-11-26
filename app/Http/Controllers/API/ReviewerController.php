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
                return $this->errorResponse('Validation failed. '.$validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
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

            return $this->successResponse($user,'Reviewer added.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function allReviewer()
    {
        try {
            $reviewers = User::where('role', 'reviewer')
                ->withCount(['verifiedAccounts','verifiedTasks','verifiedPerformance'])
                ->paginate(10);
//            ->get();
            if ($reviewers->isEmpty()) {
                return $this->errorResponse('There are no reviewers.', Response::HTTP_NOT_FOUND);
            }

            return $this->successResponse($reviewers,'Reviewers list.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return $this->errorResponse('Validation failed. '.$validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $reviewer = User::where('role', 'reviewer')->findOrFail($id);

            $reviewer->update(['status' => $request->status]);

            Mail::to($reviewer->email)->send(new AccountbannedMail($reviewer, $request->status, $request->message));

            return $this->successResponse($reviewer,"Reviewer account has been {$request->status}.", Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Record not found. '.$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function viewReviewer($id)
    {
        try {
            $reviewer = User::with('country:id,name,flag')->where('role', 'reviewer')
                ->select('id','name','email','phone','country_id')
                ->findOrFail($id);

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
            return $this->errorResponse('Record not found. '.$e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.'.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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

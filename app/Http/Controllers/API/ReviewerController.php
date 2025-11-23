<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Mail\AccountbannedMail;
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

            return response()->json([
                'status'  => true,
                'message' => 'Reviewer retrieved successfully.',
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
                'message' => 'Failed to retrieve reviewer.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}

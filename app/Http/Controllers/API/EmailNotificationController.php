<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BulkEmail;
use App\Models\BulkNotification;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;

class EmailNotificationController extends Controller
{
    public function bulkEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $data = $request->validate([
                'subject' => 'required',
                'body' => 'required',
            ]);

            $email = BulkEmail::create($data);

            $users = User::all();

            foreach ($users as $userss) {
                Mail::raw($data['body'], function ($message) use ($userss, $data) {
                    $message->to($userss->email)
                        ->subject($data['subject']);
                });
            }
            DB::commit();

            return $this->successResponse($email, 'Email sent successfully', Response::HTTP_CREATED);
        }
        catch (\Exception $e){
            DB::rollback();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function bulkNotification(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $data = $request->validate([
                'message' => 'required',
            ]);

            $email = BulkNotification::create($data);

            $users = User::all();

            foreach ($users as $userss) {
                $userss->notify(new UserNotification(
                    $data['message'],
                    $data['message'],
                ));
            }

            DB::commit();

            return $this->successResponse($email, 'Notification sent successfully', Response::HTTP_CREATED);
        }
        catch (\Exception $e){
            DB::rollback();
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

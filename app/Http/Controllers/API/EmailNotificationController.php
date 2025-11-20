<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BulkEmail;
use App\Models\BulkNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailNotificationController extends Controller
{
    public function bulkEmail(Request $request)
    {
        try {
            $data = $request->validate([
                'subject' => 'required',
                'body' => 'required',
            ]);

            $email = BulkEmail::create($data);

            return $this->successResponse($email, 'Email sent successfully', Response::HTTP_CREATED);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function bulkNotification(Request $request)
    {
        try {
            $data = $request->validate([
                'message' => 'required',
            ]);

            $email = BulkNotification::create($data);

            return $this->successResponse($email, 'Notification sent successfully', Response::HTTP_CREATED);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

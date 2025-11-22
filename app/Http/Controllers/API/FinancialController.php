<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TaskPerformer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class FinancialController extends Controller
{
    public function financialList()
    {
        try {
            $finance = TaskPerformer::with([
                'performer:id,name,email,phone',
                'task:id,country_id,sms_id,total_price',
                'task.engagement:id,engagement_name',
                'task.country:id,name,flag'
            ])->where('status', 'pending')->get();

            $completed = TaskPerformer::with([
                'performer:id,name,email,phone',
                'task:id,country_id,sms_id,total_price',
                'task.engagement:id,engagement_name',
                'task.country:id,name,flag'
            ])->where('status', 'completed')->get();

            $blocked = TaskPerformer::with([
                'performer:id,name,email,phone',
                'task:id,country_id,sms_id,total_price',
                'task.engagement:id,engagement_name',
                'task.country:id,name,flag'
            ])->where('status', 'blocked')->get();

            return $this->successResponse([
                'Pending Approval' => $finance,
                'Completed' => $completed,
                'Blocked' => $blocked
            ], 'Status updated successfully', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ' .$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateFinancial(Request $request, $taskPerformer_id)
    {
        try {
            $tp = TaskPerformer::where('id', $taskPerformer_id)->first();

            $data = $request->validate([
                'status' => 'required|in:completed,blocked',
            ]);

            $data['verified_by'] = Auth::id();

            $tp->update($data);

            return $this->successResponse($tp, 'Status updated successfully', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

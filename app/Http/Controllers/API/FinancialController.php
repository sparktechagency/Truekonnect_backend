<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TaskPerformer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Exceptions\JWTException;

class FinancialController extends Controller
{
    public function financialList()
    {
        try {
            $finance = TaskPerformer::with([
                'performer:id,name,email,phone,avatar',
                'task:id,country_id,sms_id,total_price',
                'task.engagement:id,engagement_name',
                'task.country:id,name,flag'
            ])->where('status', 'pending')
                ->paginate('10');

            $completed = TaskPerformer::with([
                'performer:id,name,email,phone,avatar',
                'task:id,country_id,sms_id,total_price',
                'task.engagement:id,engagement_name',
                'task.country:id,name,flag'
            ])->where('status', 'completed')
                ->paginate('10');

            $blocked = TaskPerformer::with([
                'performer:id,name,email,phone,avatar',
                'task:id,country_id,sms_id,total_price',
                'task.engagement:id,engagement_name',
                'task.country:id,name,flag'
            ])->where('status', 'blocked')
                ->paginate('10');

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
        DB::beginTransaction();
        try {
            $tp = TaskPerformer::where('id', $taskPerformer_id)->first();

            $data = $request->validate([
                'status' => 'required|in:completed,blocked',
            ]);

            $data['verified_by'] = Auth::id();

            $tp->update($data);
            DB::commit();

            return $this->successResponse($tp, 'Status updated successfully', Response::HTTP_OK);
        }
        catch (JWTException $e){
            DB::rollback();
            return $this->errorResponse('Something went wrong. ' .$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        catch (\Exception $e){
            DB::rollback();
            return $this->errorResponse('Something went wrong. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

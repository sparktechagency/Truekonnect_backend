<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PerformanceAnalytics extends Controller
{
    public function index(Request $request)
    {
        try {
        $data = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $fromDate = $data['from_date'] ?? null;
        $toDate = $data['to_date'] ?? null;

        $applyDateRange = function ($query, $from, $to, $table = null) {
            $column = $table ? "$table.created_at" : 'created_at';

            if ($from) {
                $query->whereDate($column, '>=', $from);
            }
            if ($to) {
                $query->whereDate($column, '<=', $to);
            }
            return $query;
        };

        $totalPerformance = $applyDateRange(User::where('role', 'performer')->where('country_id', $data['country_id']), $fromDate, $toDate)->count();
        $totalCreators = $applyDateRange(User::where('role', 'brand')->where('country_id', $data['country_id']), $fromDate, $toDate)->count();
        $totalReviewer = $applyDateRange(User::where('role', 'reviewer')->where('country_id', $data['country_id']), $fromDate, $toDate)->count();
        $totalTask = $applyDateRange(Task::where('country_id', $data['country_id']), $fromDate, $toDate)->count();
        $totalOrder = $applyDateRange(
            TaskPerformer::join('tasks', 'task_performers.task_id', '=', 'tasks.id')
                ->where('tasks.country_id', $data['country_id']),
            $fromDate,
            $toDate,
            'task_performers' // specify table for created_at
        )->count();
        $totalWithdrawal = $applyDateRange(
            Withdrawal::join('users', 'withdrawals.user_id', '=', 'users.id')
                ->where('users.country_id', $data['country_id']),
            $fromDate,
            $toDate,
            'withdrawals' // specify table for created_at
        )->count();
        $totalTokenConversion = $applyDateRange(User::where('country_id', $data['country_id']), $fromDate, $toDate)->sum('convert_token');
        $totalRevenue = $applyDateRange(Payment::join('users', 'payments.user_id', '=', 'users.id')
            ->where('users.country_id', $data['country_id'])->where('payments.status', 'paid'), $fromDate, $toDate, 'payments')->sum('payments.amount');
        $totalRevenueDistribution = $applyDateRange(
            Withdrawal::join('users', 'withdrawals.user_id', '=', 'users.id')
                ->where('users.country_id', $data['country_id']),
            $fromDate,
            $toDate,
            'withdrawals' // specify table for created_at
        )->sum('withdrawals.amount');

        $response = [
            'totalPerformer' => $totalPerformance,
            'totalCreators' => $totalCreators,
            'totalReviewer' => $totalReviewer,
            'totalTask' => $totalTask,
            'totalOrder' => $totalOrder,
            'totalWithdrawal' => $totalWithdrawal,
            'totalRevenue' => $totalRevenue,
            'totalTokenConversion' => $totalTokenConversion,
            'totalRevenueDistribution' => $totalRevenueDistribution,
        ];
        return $this->successResponse($response, 'Performance Details', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ' .$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

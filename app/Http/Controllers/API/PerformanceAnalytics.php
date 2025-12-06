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
                if ($from) $query->whereDate($column, '>=', $from);
                if ($to) $query->whereDate($column, '<=', $to);
                return $query;
            };

            // --- Performers ---
            $totalPerformers = User::where('role', 'performer')->where('country_id', $data['country_id'])->count();
            $periodPerformers = $applyDateRange(User::where('role', 'performer')->where('country_id', $data['country_id']), $fromDate, $toDate)->count();

            // --- Creators ---
            $totalCreators = User::where('role', 'brand')->where('country_id', $data['country_id'])->count();
            $periodCreators = $applyDateRange(User::where('role', 'brand')->where('country_id', $data['country_id']), $fromDate, $toDate)->count();

            // --- Reviewers ---
            $totalReviewers = User::where('role', 'reviewer')->where('country_id', $data['country_id'])->count();
            $periodReviewers = $applyDateRange(User::where('role', 'reviewer')->where('country_id', $data['country_id']), $fromDate, $toDate)->count();

            // --- Tasks ---
            $totalTasks = Task::where('country_id', $data['country_id'])->count();
            $periodTasks = $applyDateRange(Task::where('country_id', $data['country_id']), $fromDate, $toDate)->count();

            // --- Orders ---
            $totalOrders = TaskPerformer::join('tasks', 'task_performers.task_id', '=', 'tasks.id')
                ->where('tasks.country_id', $data['country_id'])->count();
            $periodOrders = $applyDateRange(
                TaskPerformer::join('tasks', 'task_performers.task_id', '=', 'tasks.id')
                    ->where('tasks.country_id', $data['country_id']),
                $fromDate, $toDate, 'task_performers'
            )->count();

            // --- Withdrawals ---
            $totalWithdrawals = Withdrawal::join('users', 'withdrawals.user_id', '=', 'users.id')
                ->where('users.country_id', $data['country_id'])->count();
            $periodWithdrawals = $applyDateRange(
                Withdrawal::join('users', 'withdrawals.user_id', '=', 'users.id')
                    ->where('users.country_id', $data['country_id']),
                $fromDate, $toDate, 'withdrawals'
            )->count();

            // --- Token Conversion ---
            $totalTokenConversion = User::where('country_id', $data['country_id'])->sum('convert_token');
            $periodTokenConversion = $applyDateRange(User::where('country_id', $data['country_id']), $fromDate, $toDate)->sum('convert_token');

            // --- Revenue ---
            $totalRevenue = Payment::join('users', 'payments.user_id', '=', 'users.id')
                ->where('users.country_id', $data['country_id'])
                ->where('payments.status', 'paid')->sum('payments.amount');
            $periodRevenue = $applyDateRange(
                Payment::join('users', 'payments.user_id', '=', 'users.id')
                    ->where('users.country_id', $data['country_id'])
                    ->where('payments.status', 'paid'),
                $fromDate, $toDate, 'payments'
            )->sum('payments.amount');

            // --- Revenue Distribution ---
            $totalRevenueDistribution = Withdrawal::join('users', 'withdrawals.user_id', '=', 'users.id')
                ->where('users.country_id', $data['country_id'])->sum('withdrawals.amount');
            $periodRevenueDistribution = $applyDateRange(
                Withdrawal::join('users', 'withdrawals.user_id', '=', 'users.id')
                    ->where('users.country_id', $data['country_id']),
                $fromDate, $toDate, 'withdrawals'
            )->sum('withdrawals.amount');

            $response = [
                'total_performers'=>$totalPerformers,
                'total_performers_period'=>$periodPerformers,
                'total_creators' => $totalCreators,
                'total_creators_period' => $periodCreators,
                'total_reviewers' => $totalReviewers,
                'total_reviewers_period' => $periodReviewers,
                'total_tasks' => $totalTasks,
                'total_tasks_period' => $periodTasks,
                'total_orders' => $totalOrders,
                'total_orders_period' => $periodOrders,
                'total_withdrawals' => $totalWithdrawals,
                'total_withdrawals_period' => $periodWithdrawals,
                'total_token_conversion' => $totalTokenConversion,
                'total_token_conversion_period' => $periodTokenConversion,
                'total_revenue' => $totalRevenue,
                'total_revenue_period' => $periodRevenue,
                'total_revenue_distribution' => $totalRevenueDistribution,
                'total_revenue_distribution_period' => $periodRevenueDistribution,
            ];


            return $this->successResponse($response, 'Performance Details', Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

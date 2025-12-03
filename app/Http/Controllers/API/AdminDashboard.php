<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;

class AdminDashboard extends Controller
{
    public function adminDashboard()
    {
        try {
            $totalUser = User::whereIn('role',['brand','performer'])->where('status','active')->count();
            $totalRevenue = Payment::where('status','paid')->sum('amount');

            $startWeek = Carbon::now()->startOfWeek();
            $endWeek = Carbon::now()->endOfWeek();

            $weeklyRevenuePerDay = Payment::where('status','paid')
                ->whereBetween('created_at', [$startWeek, $endWeek])
                ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

            $weeklyRevenue = Payment::where('status', 'paid')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->selectRaw('WEEK(created_at, 1) as week, SUM(amount) as total')
                ->groupBy('week')
                ->orderBy('week')
                ->get();

            return $this->successResponse([
                'totaluser' => $totalUser,
                'totalrevenue' => $totalRevenue,
                'weeklyrevenue' => $weeklyRevenue,
                'monthlyrevenue' => $weeklyRevenuePerDay,
            ],'Dashboard Information',Response::HTTP_OK);
        }catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

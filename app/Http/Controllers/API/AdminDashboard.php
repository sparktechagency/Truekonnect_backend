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
                ->pluck('total','day');

            $allDays = [];
            $current = $startWeek->copy();
            while ($current->lte($endWeek)) {
                $dayName = $current->format('D'); // Mon, Tue, Wed ...
                $allDays[$dayName] = $weeklyRevenuePerDay[$current->format('Y-m-d')] ?? 0;
                $current->addDay();
            }

            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

// Get revenue per day
            $dailyRevenue = Payment::where('status', 'paid')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->selectRaw('DAY(created_at) as day, SUM(amount) as total')
                ->groupBy('day')
                ->pluck('total','day'); // key = day number, value = total

// Fill all days of the month with 0 if no data
            $allMonthDays = [];
            $current = $startOfMonth->copy();
            while ($current->lte($endOfMonth)) {
                $dayNumber = $current->day;
                $allMonthDays['Day '.$dayNumber] = $dailyRevenue[$dayNumber] ?? 0;
                $current->addDay();
            }

//            dd($allMonthDays);

            return $this->successResponse([
                'totaluser' => $totalUser,
                'totalrevenue' => $totalRevenue,
                'weeklyrevenue' => $allDays,
                'monthlyrevenue' => $allMonthDays,
            ],'Dashboard Information',Response::HTTP_OK);
        }catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

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
            $totalUser = User::whereIn('role',['brand','performer'])
                ->where('status','active')
                ->count();

            $totalRevenue = Payment::where('status','paid')->sum('amount');

            // Weekly revenue
            $startWeek = Carbon::now()->startOfWeek();
            $endWeek = Carbon::now()->endOfWeek();

            $weeklyRevenuePerDay = Payment::where('status','paid')
                ->whereBetween('created_at', [$startWeek, $endWeek])
                ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
                ->groupBy('day')
                ->pluck('total','day');

            $weeklyRevenue = [];
            $current = $startWeek->copy();
            while ($current->lte($endWeek)) {
                $weeklyRevenue[] = [
                    'day' => $current->format('D'),
                    'total' => $weeklyRevenuePerDay[$current->format('Y-m-d')] ?? 0
                ];
                $current->addDay();
            }

            // Monthly revenue
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

            $dailyRevenue = Payment::where('status', 'paid')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->selectRaw('DAY(created_at) as day, SUM(amount) as total')
                ->groupBy('day')
                ->pluck('total','day');

            $monthlyRevenue = [];
            $current = $startOfMonth->copy();
            while ($current->lte($endOfMonth)) {
                $monthlyRevenue[] = [
                    'day' => $current->day,
                    'total' => $dailyRevenue[$current->day] ?? 0
                ];
                $current->addDay();
            }

            return $this->successResponse([
                'totaluser' => $totalUser,
                'totalrevenue' => $totalRevenue,
                'weeklyrevenue' => $weeklyRevenue,
                'monthlyrevenue' => $monthlyRevenue,
            ], 'Dashboard Information', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.', $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

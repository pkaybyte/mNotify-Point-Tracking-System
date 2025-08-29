<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PointAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        $startDateInput = $request->get('start_date');
        $endDateInput = $request->get('end_date');
        
        if ($startDateInput && $endDateInput) {
            $startDate = Carbon::parse($startDateInput)->startOfDay();
            $endDate = Carbon::parse($endDateInput)->endOfDay();
        } else {
            $startDate = match($period) {
                'month' => Carbon::now()->startOfMonth(),
                'quarter' => Carbon::now()->startOfQuarter(),
                'year' => Carbon::now()->startOfYear(),
                default => Carbon::now()->startOfMonth(),
            };
            $endDate = Carbon::now()->endOfDay();
        }

        $users = User::withSum(['receivedPoints' => function($query) use ($startDate, $endDate) {
            $query->where('status', 'verified')
                  ->whereBetween('created_at', [$startDate, $endDate]);
        }], 'points')
        // Filter out HR users (by email or name containing HR/hr)
        ->where(function($query) {
            $query->where('email', 'not like', '%hr@%')
                  ->where('email', 'not like', '%hr.%')
                  ->where('name', 'not like', '%hr%')
                  ->where('name', 'not like', '%HR%')
                  ->where('name', 'not like', '%Human Resources%')
                  ->where('role', '!=', 'hr'); // In case hr role exists
        })
        ->get()
        ->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'total_points' => $user->received_points_sum_points ?? 0
            ];
        });

        return response()->json($users);
    }

    public function show()
    {
        return Inertia::render('Leaderboard');
    }
}
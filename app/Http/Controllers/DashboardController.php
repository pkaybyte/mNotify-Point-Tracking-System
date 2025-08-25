<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PointAssignment;

class DashboardController extends Controller
{
    public function getDashboardData()
    {
        $user = Auth::user();
        
        // Count verified points for the current user
        $verifiedPoints = PointAssignment::where('recipient_id', $user->id)
            ->where('status', 'verified')
            ->count();
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'verifiedPoints' => $verifiedPoints,
        ]);
    }
}

// =============================================================================
// UserController.php - Create this new controller or add to existing one
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function getUsers()
    {
        // Get all users except the current user for the dropdown
        $users = User::where('id', '!=', Auth::id())
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get();
        
        return response()->json($users);
    }
}
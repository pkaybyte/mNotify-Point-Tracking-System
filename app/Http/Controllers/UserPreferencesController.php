<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    /**
     * Get user email preferences
     */
    public function getEmailPreferences()
    {
        $user = Auth::user();
        
        return response()->json([
            'email_preferences' => [
                'email_on_point_received' => $user->email_on_point_received,
                'email_on_point_verified' => $user->email_on_point_verified,
                'email_on_pending_points' => $user->email_on_pending_points, // Only relevant for supervisors
            ]
        ]);
    }

    /**
     * Update user email preferences
     */
    public function updateEmailPreferences(Request $request)
    {
        $request->validate([
            'email_on_point_received' => 'sometimes|boolean',
            'email_on_point_verified' => 'sometimes|boolean',
            'email_on_pending_points' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        
        // Only update fields that are provided in the request
        $updateData = [];
        
        if ($request->has('email_on_point_received')) {
            $updateData['email_on_point_received'] = $request->email_on_point_received;
        }
        
        if ($request->has('email_on_point_verified')) {
            $updateData['email_on_point_verified'] = $request->email_on_point_verified;
        }
        
        if ($request->has('email_on_pending_points')) {
            $updateData['email_on_pending_points'] = $request->email_on_pending_points;
        }

        // Update user preferences
        $user->update($updateData);

        return response()->json([
            'message' => 'Email preferences updated successfully',
            'email_preferences' => [
                'email_on_point_received' => $user->email_on_point_received,
                'email_on_point_verified' => $user->email_on_point_verified,
                'email_on_pending_points' => $user->email_on_pending_points,
            ]
        ]);
    }

    /**
     * Reset email preferences to default (all enabled)
     */
    public function resetEmailPreferences()
    {
        $user = Auth::user();
        
        $user->update([
            'email_on_point_received' => true,
            'email_on_point_verified' => true,
            'email_on_pending_points' => true,
        ]);

        return response()->json([
            'message' => 'Email preferences reset to default',
            'email_preferences' => [
                'email_on_point_received' => true,
                'email_on_point_verified' => true,
                'email_on_pending_points' => true,
            ]
        ]);
    }

    /**
     * Get all user preferences (can be extended for other preferences)
     */
    public function getAllPreferences()
    {
        $user = Auth::user();
        
        return response()->json([
            'user_preferences' => [
                'email_notifications' => [
                    'email_on_point_received' => $user->email_on_point_received,
                    'email_on_point_verified' => $user->email_on_point_verified,
                    'email_on_pending_points' => $user->email_on_pending_points,
                ],
                // You can add other preference categories here in the future
                // 'dashboard_settings' => [...],
                // 'notification_settings' => [...],
            ]
        ]);
    }
}
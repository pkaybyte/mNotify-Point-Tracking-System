<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    public function getRecentLogs()
    {
        $logs = AuditLog::with('user:id,name')
            ->where(function ($query) {
                // Show logs related to current user or created by current user
                $query->where('user_id', Auth::id())
                      ->orWhereJsonContains('data->recipient_id', Auth::id());
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $log->user->name ?? 'Unknown',
                    'created_at' => $log->created_at->toISOString(),
                    'data' => $log->data,
                ];
            });
        
        return response()->json($logs);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->select(['id', 'user_id', 'actor_name', 'actor_email', 'action', 'description', 'ip_address', 'metadata', 'created_at'])
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            }))
            ->when($request->user_id, fn ($q, $userId) => $q->where('user_id', $userId))
            // Use direct comparisons so the created_at index is used (whereDate wraps in DATE() which prevents index usage)
            ->when($request->date_from, fn ($q, $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($request->date_to, fn ($q, $date) => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'user_id', 'date_from', 'date_to']),
            'users' => fn () => User::orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{

    /**
     * List audit logs with search, filters and pagination.
     */
    public function index(Request $request)
    {
        ensureAdmin($request);

        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'action' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $query = AuditLog::query()->with('user:id,name,email');

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%")
                    ->orWhere('model_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $query
            ->when(
                $validated['action'] ?? null,
                fn ($q, $action) => $q->where('action', $action)
            )
            ->when(
                $validated['user_id'] ?? null,
                fn ($q, $userId) => $q->where('user_id', $userId)
            )
            ->when(
                $validated['start_date'] ?? null,
                fn ($q, $date) => $q->whereDate('created_at', '>=', $date)
            )
            ->when(
                $validated['end_date'] ?? null,
                fn ($q, $date) => $q->whereDate('created_at', '<=', $date)
            );

        $logs = $query
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}


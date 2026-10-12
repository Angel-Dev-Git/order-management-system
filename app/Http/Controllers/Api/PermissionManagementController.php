<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermissionManagementController extends Controller
{
    // private function ensureAdmin(Request $request): void
    // {
    //     abort_unless(
    //         strtolower($request->user()?->role?->name ?? '') === 'admin',
    //         403,
    //         'Only administrators can manage permissions.'
    //     );
    // }

    /**
     * List all permissions grouped by module.
     */
    public function index(Request $request)
    {
        ensureAdmin($request);

        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get(['id', 'name', 'module', 'description'])
            ->groupBy('module');

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Get permissions assigned to a particular role.
     */
    public function showRolePermissions(Request $request, Role $role)
    {
        ensureAdmin($request);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                ],
                'permission_ids' => $role->permissions()
                    ->pluck('permissions.id')
                    ->map(fn ($id) => (int) $id)
                    ->values(),
            ],
        ]);
    }

    /**
     * Replace the permissions assigned to a role.
     */
    public function updateRolePermissions(Request $request,Role $role) {
        ensureAdmin($request);
        if (strtolower($role->name) === 'admin') {
            throw ValidationException::withMessages([
                'role' => ['The already built Admin role permissions cannot be changed here.'],
            ]);
        }
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'required|integer|distinct|exists:permissions,id',
        ]);

        DB::transaction(function () use ($role, $validated) {
            $role->permissions()->sync($validated['permission_ids']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully.',
            'data' => [
                'role_id' => $role->id,
                'permission_ids' => $role->permissions()->pluck('permissions.id')->map(fn($id) => (int)$id)->values(),
            ],
        ]);
    }
}

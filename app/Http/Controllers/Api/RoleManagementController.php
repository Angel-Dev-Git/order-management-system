<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RoleManagementController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            strtolower($request->user()?->role?->name ?? '') === 'admin',
            403,
            'Only administrators can manage roles.'
        );
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $roles = Role::query()->withCount('users')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required','string','max:100','regex:/^[a-zA-Z][a-zA-Z0-9 _-]*$/',Rule::unique('roles', 'name')],
        ]);

        $name = trim($validated['name']);
        // Built-in role names are reserved.
        if (in_array(strtolower($name), ['admin', 'manager', 'staff'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This role name is reserved',
            ], 422);
        }
        $role = Role::create(['name' => $name]);
        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => $role,
        ], 201);
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureAdmin($request);

        // Keep built-in role names stable because authorization
        if (in_array(strtolower($role->name), ['admin', 'manager', 'staff'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Built-in roles cannot be renamed.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required','string','max:100','regex:/^[a-zA-Z][a-zA-Z0-9 _-]*$/',Rule::unique('roles', 'name')->ignore($role->id)],
        ]);

        $name = trim($validated['name']);

        if (in_array(strtolower($name), ['admin', 'manager', 'staff'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This role name is reserved.',
            ], 422);
        }

        $role->update(['name' => $name]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role->fresh(),
        ]);
    }

    public function destroy(Request $request, Role $role)
    {
        $this->ensureAdmin($request);

        if (in_array(strtolower($role->name), ['admin', 'manager', 'staff'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Built-in roles cannot be deleted.',
            ], 422);
        }

        if (User::where('role_id',$role->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This role is assigned to users and cannot be deleted.',
            ], 422);
        }
        $role->delete();
        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }
    
}

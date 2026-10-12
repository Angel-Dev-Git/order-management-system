<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\UserRequest;

class UserManagementController extends Controller
{
    // private function ensureAdmin(Request $request): void
    // {
    //     abort_unless(
    //         strtolower($request->user()?->role?->name ?? '') === 'admin',
    //         403,
    //         'Only administrators can manage users.'
    //     );
    // }

    // List users with search and pagination
    public function index(Request $request)
    {
        ensureAdmin($request);

        $users = User::with('role')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role_id'), function ($query) use ($request) {
                $query->where('role_id', $request->role_id);
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    // Return assignable roles
    public function roles(Request $request)
    {
        ensureAdmin($request);

        $roles = Role::query()
            ->whereIn('name', ['Manager', 'Staff'])
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    // Create a Manager or Staff user
    public function store(UserRequest $request)
    {
        ensureAdmin($request);

        $validated = $request->validated();

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load('role'),
        ], 201);
    }

    // Update user details and role
    public function update(UserRequest $request, User $user)
    {
        ensureAdmin($request);

        $validated = $request->validated();

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // // Prevent an Admin from deactivating their own account
        // if (
        //     $user->id === $request->user()->id &&
        //     ($validated['status'] ?? null) === 'inactive'
        // ) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'You cannot deactivate your own account.',
        //     ], 422);
        // }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->fresh()->load('role'),
        ]);
    }
}

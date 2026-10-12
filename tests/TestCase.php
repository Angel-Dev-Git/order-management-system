<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
      public function createTestUser(string $roleName = 'Admin'): User
{
    $role = Role::firstOrCreate([
        'name' => $roleName,
    ]);

    $allPermissions = [
        'dashboard.view',
        'reports.view',
        'orders.view_all',
        'orders.view_assigned',
        'orders.create',
        'orders.update',
        'orders.assign',
        'orders.change_status',
        'orders.delete',
        'orders.add_notes',
        'customers.view',
        'customers.create',
        'customers.update',
        'customers.delete',
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'users.manage',
        'roles.manage',
        'permissions.manage',
        'audit_logs.view',
    ];

    $rolePermissions = match ($roleName) {
        'Admin' => $allPermissions,

        'Manager' => [
            'dashboard.view',
            'reports.view',
            'orders.view_all',
            'orders.create',
            'orders.update',
            'orders.assign',
            'orders.change_status',
            'orders.add_notes',
            'customers.view',
            'customers.create',
            'customers.update',
            'products.view',
        ],

        'Staff' => [
            'dashboard.view',
            'orders.view_assigned',
            'orders.change_status',
            'orders.add_notes',
            'customers.view',
            'products.view',
        ],

        default => [],
    };

    $permissionIds = collect($rolePermissions)
        ->map(function (string $name) {
            return Permission::firstOrCreate(
                ['name' => $name],
                ['module' => explode('.', $name)[0]]
            )->id;
        });

    $role->permissions()->sync($permissionIds);

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}
    
}

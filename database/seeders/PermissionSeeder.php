<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $definitions = [
            // Dashboard and reports
            'dashboard.view'          => ['Dashboard', 'View dashboard'],
            'reports.view'            => ['Reports', 'View reports'],

            // Orders
            'orders.view_all'         => ['Orders', 'View all orders'],
            'orders.view_assigned'    => ['Orders', 'View assigned orders'],
            'orders.create'            => ['Orders', 'Create orders'],
            'orders.update'            => ['Orders', 'Edit orders'],
            'orders.assign'            => ['Orders', 'Assign orders'],
            'orders.change_status'     => ['Orders', 'Change order status'],
            'orders.delete'            => ['Orders', 'Delete orders'],
            'orders.add_notes'         => ['Orders', 'Add order notes'],

            // Customers
            'customers.view'           => ['Customers', 'View customers'],
            'customers.create'         => ['Customers', 'Create customers'],
            'customers.update'         => ['Customers', 'Edit customers'],
            'customers.delete'         => ['Customers', 'Delete customers'],

            // Products
            'products.view'            => ['Products', 'View products'],
            'products.create'          => ['Products', 'Create products'],
            'products.update'          => ['Products', 'Edit products'],
            'products.delete'          => ['Products', 'Delete products'],

            // Administration and audit
            'users.manage'             => ['Administration', 'Manage users'],
            'roles.manage'             => ['Administration', 'Manage roles'],
            'permissions.manage'        => ['Administration', 'Manage permissions'],
            'audit_logs.view'           => ['Administration', 'View audit logs'],
        ];

        DB::transaction(function () use ($definitions) {
            $permissions = [];

            foreach ($definitions as $name => [$module, $description]) {
                $permissions[$name] = Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'module' => $module,
                        'description' => $description,
                    ]
                );
            }

            $rolePermissions = [
                'admin' => array_keys($definitions),

                'manager' => [
                    'dashboard.view',
                    'reports.view',
                    'orders.view_all',
                    'orders.create',
                    'orders.update',
                    'orders.assign',
                    'orders.change_status',
                    'orders.add_notes',
                    'customers.view',
                ],

                'staff' => [
                    'dashboard.view',
                    'orders.view_assigned',
                    'orders.change_status',
                    'orders.add_notes',
                    'customers.view',
                ],
            ];

            foreach ($rolePermissions as $roleName => $permissionNames) {
                $role = Role::query()
                    ->whereRaw('LOWER(name) = ?', [$roleName])
                    ->first();

                if (!$role) {
                    continue;
                }

                $permissionIds = collect($permissionNames)
                    ->map(fn ($name) => $permissions[$name]->id)
                    ->all();

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}

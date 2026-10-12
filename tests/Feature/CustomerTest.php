<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase,WithFaker;
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
    
      private function createTestUser(string $roleName = 'Admin'): User
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
public function test_customer_listing(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    Customer::create([
        'name' => 'Test Customer One',
        'email' => 'customer1@example.com',
        'phone' => '9876543210',
        'address' => 'Jaipur',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/customers');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'data',
                'current_page',
                'per_page',
                'total',
            ],
        ]);
}

public function test_customer_search(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    Customer::create([
        'name' => 'Unique Search Customer',
        'email' => 'unique-customer@example.com',
        'phone' => '9876543210',
        'address' => 'Jaipur',
        'status' => 'active',
    ]);

    $response = $this->getJson(
        '/api/customers?search=Unique Search Customer'
    );

    $response->assertOk()
        ->assertJsonFragment([
            'name' => 'Unique Search Customer',
        ]);
}
// create customer

public function test_customer_creation(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', [
        'name' => 'New Test Customer',
        'email' => 'new-customer@example.com',
        'phone' => '9876543210',
        'address' => 'Jaipur',
        'status' => 'active',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Customer created successfully.'
        )
        ->assertJsonPath(
            'data.name',
            'New Test Customer'
        );

    $this->assertDatabaseHas('customers', [
        'email' => 'new-customer@example.com',
        'name' => 'New Test Customer',
    ]);
}
// validation

public function test_customer_validation(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/customers', [
        'name' => '',
        'email' => 'invalid-email',
        'status' => 'unknown',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'email',
            'status',
        ]);
}
// delete

public function test_customer_show(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $customer = Customer::create([
        'name' => 'Show Customer',
        'email' => 'show-customer@example.com',
        'phone' => '9876543210',
        'address' => 'Jaipur',
        'status' => 'active',
    ]);

    $this->getJson("/api/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.name', 'Show Customer');
}

public function test_customer_update(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $customer = Customer::create([
        'name' => 'Old Customer',
        'email' => 'old-customer@example.com',
        'status' => 'active',
    ]);

    $this->putJson("/api/customers/{$customer->id}", [
        'name' => 'Updated Customer',
        'email' => 'updated-customer@example.com',
        'status' => 'inactive',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Updated Customer');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Updated Customer',
        'status' => 'inactive',
    ]);
}

public function test_customer_delete(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $customer = Customer::create([
        'name' => 'Delete Customer',
        'email' => 'delete-customer@example.com',
        'status' => 'active',
    ]);

    $this->deleteJson("/api/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('customers', [
        'id' => $customer->id,
    ]);
}

}

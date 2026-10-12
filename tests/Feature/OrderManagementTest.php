<?php

namespace Tests\Feature;

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
use Tests\TestCase;
class OrderManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

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
    //     public function test_user_info(): void
    // {
    //     // Assuming you have a user with ID 1
    //     $user = User::factory()->create(['id' => 1]);

    //     $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

    //     $response->assertStatus(200)
    //              ->assertJson([
    //                  'id' => 1,
    //                  'name' => $user->name,
    //                  'email' => $user->email,
    //              ]);
    // }
public function test_user_info(): void
{
    $user = $this->createTestUser();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', $user->name)
        ->assertJsonPath('data.user.email', $user->email);
}
    public function test_user_logout(): void
    {
        // Assuming you have a user with ID 1
        $user = User::factory()->create(['id' => 1]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Logout successful.',
                 ]);
    }
    public function test_user_login(): void
    {
        // Create a user for testing
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Login successful',
                     'data'=>$user
                 ]);
    }
    public function test_user_login_invalid_credentials(): void
    {
        // Create a user for testing
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Invalid email or password.',
                 ]);
    }  
    public function test_user_login_validation(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email', 'password']);
    }       
    public function test_user_login_missing_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email', 'password']);
    }
    // public function test_order_creation(): void
    // {
    //     $response = $this->postJson('/api/orders', [
    //         'order_number' => 'ORD123456',
    //         'customer_id' => 1,
    //         'order_date' => now()->toDateString(),
    //         'status' => 'Pending',
    //     ]);

    //     $response->assertStatus(201)
    //              ->assertJsonStructure([
    //                  'success',
    //                  'message',
    //                  'data' => [
    //                      'id',
    //                      'order_number',
    //                      'customer_id',
    //                      'order_date',
    //                      'status',
    //                      'created_at',
    //                      'updated_at',
    //                  ],
    //              ]);
    // }
    
   private function createTestOrder(array $attributes = []): Order
{
    $user = User::first() ?? $this->createTestUser();

    $customer = Customer::first() ?? Customer::create([
        'name' => 'Test Customer',
        'email' => 'customer@example.com',
        'phone' => '9876543210',
        'address' => 'Test Address',
        'status' => 'active',
    ]);

    return Order::create(array_merge([
         'order_number' => 'ORD-' . uniqid(),
    'customer_id'  => $customer->id,
    'created_by'   => $user->id,
    'assigned_to'  => null,

    'status'       => 'draft',
    'priority'     => 'normal',
    'order_date'   => now()->toDateString(),

    'subtotal'     => 100.00,
    'discount'     => 0.00,
    'tax'          => 0.00,
    'total'        => 100.00,

    'remarks'        => null,
    ], $attributes));
}
public function test_order_creation(): void
{
    $user = $this->createTestUser();

    Sanctum::actingAs($user);

    $customer = Customer::create([
        'name' => 'Test Customer',
        'email' => 'customer@example.com',
        'phone' => '9876543210',
        'address' => 'Test Address',
        'status' => 'active',
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'TEST-001',
        'description' => 'Test product',
        'price' => 100,
        'stock_quantity' => 10,
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'status' => 'draft',
        'priority' => 'normal',
        'items' => [
            [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => 2,
                'unit_price' => 100,
                'total' => 200,
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'created_by' => $user->id,
        'status' => 'draft',
        'subtotal' => 200,
    ]);

    $this->assertDatabaseHas('order_items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
}

    public  function test_order_status_update(): void
    {
        $user = $this->createTestUser();

        Sanctum::actingAs($user);
        $order = $this->createTestOrder([
            'status' => 'draft',
        ]);
        
       $response = $this->patchJson(
            "/api/orders/{$order->id}/status",
            ['status' => 'pending']
        );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }
    public function test_order_assignment(): void
    {
        $admin = $this->createTestUser('Admin');
        $staff = $this->createTestUser('Staff');

        Sanctum::actingAs($admin);
        // Create an order first
       $order = $this->createTestOrder([
            'assigned_to' => null,
        ]);

       $response = $this->patchJson(
                "/api/orders/{$order->id}/assign",
                ['assigned_to' => $staff->id]
            );

            $response->assertOk();

            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'assigned_to' => $staff->id,
            ]);
    }
   public function test_order_search(): void
{
    $user = $this->createTestUser();

    Sanctum::actingAs($user);

    // Create valid customers and orders here using their actual IDs.

    $response = $this->getJson('/api/orders?search=ORD123456');

    $response->assertOk();
}
    public function test_order_filtering(): void
    {
         $user = $this->createTestUser();

$this->actingAs($user, 'sanctum');
        // Create some orders for testing
        $this->createTestOrder([
            'order_number' => 'ORD123456',
                    'customer_id' => 1,
                    'assigned_to' => 2,
        ]);

        $this->createTestOrder([
            'order_number' => 'ORD654321',
            'customer_id' => 2,
            'assigned_to' => 3,
        ]);

        $response = $this->getJson('/api/orders?customer_id=1&assigned_to=2');

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'order_number' => 'ORD123456',
                 ])
                 ->assertJsonMissing([
                     'order_number' => 'ORD654321',
                 ]);
    }
    public function test_order_sorting(): void
    {
         $user = $this->createTestUser();

        $this->actingAs($user, 'sanctum');
        // Create some orders for testing
        $this->createTestOrder([
            'order_number' => 'ORD123456',
            'order_date' => now()->subDays(1),
        ]);

        $this->createTestOrder([
            'order_number' => 'ORD654321',
            'order_date' => now(),
        ]);
       

        $response = $this->getJson('/api/orders?sort_by=order_date&sort_direction=desc');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.order_number', 'ORD654321')
                 ->assertJsonPath('data.1.order_number', 'ORD123456');
    }
    public function test_order_pagination(): void
    {
         $user = $this->createTestUser();

$this->actingAs($user, 'sanctum');
        // Create multiple orders for testing
        // Order::factory()->count(15)->create();

        $response = $this->getJson('/api/orders?per_page=10');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data',
                     'links',
                     'meta',
                 ])
                 ->assertJsonCount(10, 'data');
    }
    public function test_order_status_history(): void
    {
         $user = $this->createTestUser();

$this->actingAs($user, 'sanctum');
        // Create an order and update its status
        $order = $this->createTestOrder([
            'status' => 'Pending',
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'Processing',
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/history");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         '*' => [
                             'id',
                             'user_id',
                             'order_id',
                             'previous_status',
                             'new_status',
                             'remarks',
                             'created_at',
                             'updated_at',
                         ],
                     ],
                 ]);
    }
   public function test_order_notes(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $order = $this->createTestOrder([
        'created_by' => $user->id,
    ]);

    $response = $this->postJson(
        "/api/orders/{$order->id}/notes",
        ['note' => 'This is a test note.']
    );

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Note added successfully.',
        ]);

    $this->assertDatabaseHas('order_notes', [
        'order_id' => $order->id,
        'note' => 'This is a test note.',
    ]);

    $response = $this->getJson("/api/orders/{$order->id}/notes");

    $response->assertOk()
        ->assertJsonFragment([
            'note' => 'This is a test note.',
        ]);
}
    public function test_order_activity(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $staff = $this->createTestUser('Staff');

    $order = $this->createTestOrder([
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    $this->patchJson("/api/orders/{$order->id}/status", [
        'status' => 'pending',
    ]);

    $this->patchJson("/api/orders/{$order->id}/assign", [
        'assigned_to' => $staff->id,
    ]);

    $response = $this->getJson("/api/orders/{$order->id}/activity");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data',
        ]);
}
   public function test_dashboard_data(): void
{
    $user = $this->createTestUser();
    Sanctum::actingAs($user);

    $this->createTestOrder(['status' => 'pending']);
    $this->createTestOrder(['status' => 'processing']);
    $this->createTestOrder(['status' => 'completed']);

    $response = $this->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data',
        ]);
}

}

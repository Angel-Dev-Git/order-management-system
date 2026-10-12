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
    $user = self::createTestUser();

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

        $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Login successful')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'token',
                ],
            ],
        ])
        ->assertJsonPath('data.user.email', 'test@example.com')
        ->assertJsonPath('data.user.name', User::where('email','test@example.com')->value('name'));
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
    $user = User::first() ?? self::createTestUser();

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
// order is created
public function test_order_creation(): void
{
    $user = self::createTestUser();

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
// update order status
public  function test_order_status_update(): void
{
    $user = self::createTestUser();

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
// assign order
public function test_order_assignment(): void
{
    $admin = self::createTestUser('Admin');
    $staff = self::createTestUser('Staff');

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
// search order
   public function test_order_search(): void
{
    $user = self::createTestUser();

    Sanctum::actingAs($user);

    // Create valid customers and orders here using their actual IDs.

    $response = $this->getJson('/api/orders?search=ORD123456');

    $response->assertOk();
}
 // filter orders
public function test_order_filtering(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);
    $customer1 = Customer::create([
        'name' => 'Customer One',
        'email' => 'customer1@example.com',
        'phone' => '9876543210',
        'address' => 'Address One',
        'status' => 'active',
    ]);

    $customer2 = Customer::create([
        'name' => 'Customer Two',
        'email' => 'customer2@example.com',
        'phone' => '9876543211',
        'address' => 'Address Two',
        'status' => 'active',
    ]);

    $assignedUser1 = self::createTestUser('Staff');
    $assignedUser2 = self::createTestUser('Staff');

    $this->createTestOrder([
        'order_number' => 'ORD123456',
        'customer_id' => $customer1->id,
        'assigned_to' => $assignedUser1->id,
    ]);

    $this->createTestOrder([
        'order_number' => 'ORD654321',
        'customer_id' => $customer2->id,
        'assigned_to' => $assignedUser2->id,
    ]);
    $response = $this->getJson("/api/orders?customer_id={$customer1->id}&assigned_to={$assignedUser1->id}");
    $response->assertOk()
        ->assertJsonFragment([
            'order_number' => 'ORD123456',
        ])
        ->assertJsonMissing([
            'order_number' => 'ORD654321',
        ]);
}
// sort orders
public function test_order_sorting(): void
{
        $user = self::createTestUser();

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
            ->assertJsonPath('data.data.0.order_number', 'ORD654321')
            ->assertJsonPath('data.data.1.order_number', 'ORD123456');
}
//   pagination 
public function test_order_pagination(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

     for ($i = 1; $i <= 15; $i++) {
        $this->createTestOrder([
            'order_number' => 'ORD-PAGE-' . $i,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);
    }

    $response = $this->getJson('/api/orders?per_page=10');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'current_page',
                'data',
                'first_page_url',
                'last_page',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'total',
            ],
        ])
        ->assertJsonCount(10, 'data.data')
        ->assertJsonPath('data.per_page', 10)
        ->assertJsonPath('data.total', 15);
}
// order status history
public function test_order_status_history(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    $order = $this->createTestOrder([
        'status' => 'pending',
    ]);

    $this->patchJson("/api/orders/{$order->id}/status", [
        'status' => 'confirmed',
    ])->assertOk();

    $response = $this->getJson("/api/orders/{$order->id}/history");

    $response->assertOk()
        ->assertJsonStructure(['success','message',
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
        ])
        ->assertJsonPath('data.0.previous_status', 'pending')
        ->assertJsonPath('data.0.new_status', 'confirmed');
}
// order notes 
   public function test_order_notes(): void
{
    $user = self::createTestUser();
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
// order activity
public function test_order_activity(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    $staff = self::createTestUser('Staff');

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
// dahsbaord
public function test_dashboard_data(): void
{
    $user = self::createTestUser();
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

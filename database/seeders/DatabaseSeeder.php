<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderNote;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        //     'password' => Hash::make('Password@123'),
        // ]);
          //Create roles first
         $this->call([RoleSeeder::class,PermissionSeeder::class,UserSeeder::class]);
         DB::transaction(function () {
            // // 1. Create roles
            // foreach (['Admin', 'Manager', 'Staff'] as $roleName) {
            //     Role::firstOrCreate(['name' => $roleName]);
            // }
            $roles = Role::pluck('id', 'name');

            // 2. Create five users
            $users = [
                // [
                //     'name' => 'OMS Admin',
                //     'email' => 'admin@oms.com',
                //     'role_id' => $roles['Admin'],
                // ],
                [
                    'name' => 'Manager One',
                    'email' => 'manager1@oms.com',
                    'role_id' => $roles['Manager'],
                ],
                [
                    'name' => 'Manager Two',
                    'email' => 'manager2@oms.com',
                    'role_id' => $roles['Manager'],
                ],
                [
                    'name' => 'Staff One',
                    'email' => 'staff1@oms.com',
                    'role_id' => $roles['Staff'],
                ],
                [
                    'name' => 'Staff Two',
                    'email' => 'staff2@oms.com',
                    'role_id' => $roles['Staff'],
                ],
            ];

            foreach ($users as $userData) {
                User::firstOrCreate(
                    ['email' => $userData['email']],
                    [
                        'name' => $userData['name'],
                        'role_id' => $userData['role_id'],
                        'password' => Hash::make('Password@123'),
                    ]
                );
            }

            $users = User::whereIn('email', [
                'admin@business.com',
                'manager1@oms.com',
                'manager2@oms.com',
                'staff1@oms.com',
                'staff2@oms.com',
            ])->get();

            // 3. Create 100 customers
            while (Customer::count() < 100) {
                $number = Customer::count() + 1;

                Customer::create([
                    'name' => fake()->name(),
                    'email' => 'customer' . $number . '@example.com',
                    'phone' => fake()->numerify('9#########'),
                    'address' => fake()->address(),
                    'status' => fake()->randomElement([
                        'active',
                        'inactive',
                    ]),
                ]);
            }

            // 4. Create 50 products
            while (Product::count() < 50) {
                $number = Product::count() + 1;

                Product::create([
                    'name' => fake()->words(3, true),
                    'sku' => 'OMS-' . strtoupper(Str::random(10)),
                    'description' => fake()->sentence(),
                    'price' => fake()->randomFloat(2, 100, 10000),
                    'stock_quantity' => fake()->numberBetween(10, 200),
                    'status' => fake()->randomElement([
                        'active',
                        'inactive',
                    ]),
                ]);
            }

            $customers = Customer::all();
            $products = Product::where('status', 'active')->get();

            if ($customers->isEmpty() || $products->isEmpty()) {
                throw new \RuntimeException(
                    'Customers and active products are required.'
                );
            }

            $statuses = [
                'draft',
                'pending',
                'confirmed',
                'processing',
                'ready',
                'completed',
                'cancelled',
            ];

            $priorities = ['low', 'normal', 'high'];

            // 5. Create 500 orders with items and activity
            while (Order::count() < 500) {
                $user = $users->random();
                $customer = $customers->random();
                $assignedUser = $users
                    ->whereIn('role_id', [
                        $roles['Manager'],
                        $roles['Staff'],
                    ])
                    ->random();

                $status = fake()->randomElement($statuses);
                $priority = fake()->randomElement($priorities);
                $orderDate = fake()->dateTimeBetween(
                    '-6 months',
                    'now'
                );

                $selectedProducts = $products->random(
                    fake()->numberBetween(
                        1,
                        min(4, $products->count())
                    )
                );

                $items = [];
                $subtotal = 0;

                foreach ($selectedProducts as $product) {
                    $quantity = fake()->numberBetween(1, 5);
                    $unitPrice = (float) $product->price;
                    $lineTotal = round(
                        $unitPrice * $quantity,
                        2
                    );

                    $subtotal += $lineTotal;

                    $items[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $lineTotal,
                    ];
                }

                $discount = round(
                    $subtotal * fake()->randomElement([
                        0,
                        0.05,
                        0.10,
                    ]),
                    2
                );

                $tax = round(
                    ($subtotal - $discount) * 0.05,
                    2
                );

                $total = round(
                    $subtotal - $discount + $tax,
                    2
                );

                $order = Order::create([
                    'order_number' => 'ORD-' . strtoupper(
                        Str::uuid()->toString()
                    ),
                    'customer_id' => $customer->id,
                    'created_by' => $user->id,
                    'assigned_to' => $assignedUser->id,
                    'status' => $status,
                    'priority' => $priority,
                    'order_date' => $orderDate->format('Y-m-d'),
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                ]);

                $order->items()->createMany($items);

                // Status history: simulate progression
                $statusIndex = array_search(
                    $status,
                    $statuses,
                    true
                );

                $historyStatuses = array_slice(
                    $statuses,
                    0,
                    $statusIndex + 1
                );

                if ($status === 'cancelled') {
                    $historyStatuses = [
                        'draft',
                        'pending',
                        'cancelled',
                    ];
                }

                $previousStatus = 'draft';

                foreach ($historyStatuses as $index => $historyStatus) {
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'previous_status' => $previousStatus,
                        'new_status' => $historyStatus,
                        'remarks' => 'Demo status activity',
                        'created_at' => now()->subDays(
                            count($historyStatuses) - $index
                        ),
                        'updated_at' => now()->subDays(
                            count($historyStatuses) - $index
                        ),
                    ]);

                    $previousStatus = $historyStatus;
                }

                // Add two notes
                foreach ([
                    'Customer order details verified.',
                    'Order progress updated by the team.',
                ] as $noteText) {
                    OrderNote::create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'note' => $noteText,
                    ]);
                }
// dd($order);
                // Audit log: order created
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'created',
                    'model_type' => Order::class,
                    'model_id' => $order->id,
                    'old_values' => null,
                    'new_values' => [
                        'status' => $order->status,
                        'priority' => $order->priority,
                        'total' => $order->total,
                    ],
                ]);

                // Audit log: assignment
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'assigned',
                    'model_type' => Order::class,
                    'model_id' => $order->id,
                    'old_values' => [
                        'assigned_to' => null,
                    ],
                    'new_values' => [
                        'assigned_to' => $assignedUser->id,
                    ],
                ]);
            }
        });

        $this->command->info(
            'dummy data created successfully.'
        );
    }
    
}

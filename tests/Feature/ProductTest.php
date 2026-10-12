<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase,WithFaker;
 
public function test_product_listing(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    Product::create([
        'name' => 'Test Laptop',
        'sku' => 'LAP-TEST-001',
        'description' => 'Test laptop',
        'price' => 50000,
        'stock_quantity' => 10,
        'status' => 'active',
    ]);

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'data',
                'current_page',
                'per_page',
                'total',
            ],
        ]);
}

public function test_product_search(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    Product::create([
        'name' => 'Unique Search Laptop',
        'sku' => 'UNIQUE-LAP-001',
        'price' => 50000,
        'stock_quantity' => 10,
        'status' => 'active',
    ]);

    $this->getJson('/api/products?search=UNIQUE-LAP-001')
        ->assertOk()
        ->assertJsonFragment([
            'sku' => 'UNIQUE-LAP-001',
        ]);
}


public function test_product_creation(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => 'New Test Product',
        'sku' => 'NEW-TEST-001',
        'description' => 'Product created during testing',
        'price' => 1500,
        'stock_quantity' => 20,
        'status' => 'active',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Product created successfully.'
        )
        ->assertJsonPath(
            'data.name',
            'New Test Product'
        );

    $this->assertDatabaseHas('products', [
        'sku' => 'NEW-TEST-001',
        'name' => 'New Test Product',
        'price' => 1500,
        'stock_quantity' => 20,
    ]);
}

public function test_product_validation(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/products', [
        'name' => '',
        'sku' => '',
        'price' => -100,
        'stock_quantity' => -5,
        'status' => 'unknown',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'sku',
            'price',
            'stock_quantity',
            'status',
        ]);
}

public function test_product_duplicate_sku_validation(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    Product::create([
        'name' => 'Existing Product',
        'sku' => 'DUPLICATE-001',
        'price' => 100,
        'stock_quantity' => 5,
        'status' => 'active',
    ]);

    $this->postJson('/api/products', [
        'name' => 'Another Product',
        'sku' => 'DUPLICATE-001',
        'price' => 200,
        'stock_quantity' => 10,
        'status' => 'active',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
}

public function test_product_show(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    $product = Product::create([
        'name' => 'Show Product',
        'sku' => 'SHOW-001',
        'price' => 1000,
        'stock_quantity' => 10,
        'status' => 'active',
    ]);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.sku', 'SHOW-001');
}

public function test_product_update(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    $product = Product::create([
        'name' => 'Old Product',
        'sku' => 'OLD-001',
        'price' => 100,
        'stock_quantity' => 5,
        'status' => 'active',
    ]);

    $this->putJson("/api/products/{$product->id}", [
        'name' => 'Updated Product',
        'sku' => 'UPDATED-001',
        'price' => 250,
        'stock_quantity' => 15,
        'status' => 'active',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Updated Product')
        ->assertJsonPath('data.sku', 'UPDATED-001');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Product',
        'sku' => 'UPDATED-001',
        'price' => 250,
        'stock_quantity' => 15,
    ]);
}

public function test_product_delete(): void
{
    $user = self::createTestUser();
    Sanctum::actingAs($user);

    $product = Product::create([
        'name' => 'Delete Product',
        'sku' => 'DELETE-001',
        'price' => 100,
        'stock_quantity' => 5,
        'status' => 'active',
    ]);

    $this->deleteJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Product deleted successfully.'
        );

    $this->assertSoftDeleted('products', [
        'id' => $product->id,
    ]);
}

}

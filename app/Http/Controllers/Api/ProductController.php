<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\ProductRequest;
class ProductController extends Controller
{
     public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    // Create product
    public function store(ProductRequest $request)
    {
        $validated = $request->validated();
       $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);
            auditLog($request->user()->id, 'create', Product::class, $product->id, null, $product);
            return $product;
        });
        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    // Show product
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    // Update product
    public function update(ProductRequest $request, Product $product)
    {
        $validated = $request->validated();
            DB::transaction(function () use ($product, $validated, $request) {
                $originalData = $product->only(['name','sku','price','stock_quantity','status']);
                $product->update($validated);
                auditLog($request->user()->id, 'update', Product::class, $product->id, $originalData, $product->fresh()->only(['name','sku','price','stock_quantity','status',
             ]));
        });
        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->fresh(),
        ]);
    }

    // delete product
    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $product->delete();
            auditLog(request()->user()->id, 'delete', Product::class, $product->id, $product, null);
        });
        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
    
}

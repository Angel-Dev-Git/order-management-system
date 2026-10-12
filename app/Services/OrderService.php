<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
USE iLLUMINATE\Pagination\LengthAwarePaginator;

class OrderService
{
    public function index(Request $request):LengthAwarePaginator{
        $user = $request->user();
        $role = $user->role;
        $canViewAll = $role?->permissions()->where('permissions.name', 'orders.view_all')->exists() ?? false;
        $canViewAssigned = $role?->permissions()->where('permissions.name', 'orders.view_assigned')->exists() ?? false;
        abort_unless($canViewAll || $canViewAssigned,403,'You do not have permission to view orders.');
        $orders = Order::with(['customer', 'assigned', 'items']);
        // Staff or assigned-only users see their own orders.
        if (!$canViewAll) {
            $orders->where('assigned_to', $user->id);
        }

            $orders->when($request->get('search') ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('assigned', function ($assignedQuery) use ($search) {
                            $assignedQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery->where('product_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->get('status') ?? null, function ($query,$status) {
                $query->where('status', $status);
            })
            ->when($request->get('priority') ?? null, function ($query,$priority) {
                $query->where('priority', $priority);
            })
            ->when($request->get('customer_id') ?? null, function ($query,$customer_id) {
                $query->where('customer_id', $customer_id);
            })
            ->when($request->get('assigned_to') ?? null, function ($query,$assigned_to) {
                $query->where('assigned_to', $assigned_to);
            })
            ->when($request->get('start_date') ?? null, function ($query,$start_date) {
                $query->whereDate('order_date', '>=', $start_date);
            })
            ->when($request->get('end_date') ?? null, function ($query,$end_date) {
                $query->whereDate('order_date', '<=', $end_date);
            });

            // $sortBy = $request->get('sort_by') ?? 'id';
            // $sortOrder = $request->get('sort_order') ?? 'desc';
            // $orders = $orders->orderBy($sortBy, $sortOrder)->paginate($request->get('per_page') ?? 10);
            $allowedSortColumns = [
                'id',
                'order_number',
                'order_date',
                'status',
                'priority',
                'total',
                'created_at',
            ];

            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = strtolower($request->input('sort_direction', 'desc'));

            if (!in_array($sortBy, $allowedSortColumns, true)) {
                $sortBy = 'id';
            }

            if (!in_array($sortOrder, ['asc', 'desc'], true)) {
                $sortOrder = 'desc';
            }

            $perPage = min(max((int) $request->input('per_page', 10), 1),100);

            return $orders->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }
    public function create(array $validated, int $userId): Order
    {
       return DB::transaction(function () use ($validated,$userId) {
            $subtotal = 0;
            $items = [];
            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Product {$product->name} is not active.",
                        ],
                    ]);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Out of stock for {$product->name}.",
                        ],
                    ]);
                }
                $pricetotal = (float)$product->price * $item['quantity'];
                $subtotal = $subtotal + $pricetotal;
               // store order items
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $pricetotal,
                ];
            }

            $discount = (float)($validated['discount'] ?? 0);
            $tax = (float)($validated['tax'] ?? 0);

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => ['Discount cannot exceed the subtotal.'],
                ]);
            }
            $total = round($subtotal - $discount + $tax, 2);

            if ($total < 0) {
                throw ValidationException::withMessages([
                    'total' => ['Order total cannot be negative.'],
                ]);
            }
            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::uuid()),
                'customer_id' => $validated['customer_id'],
                'created_by' => $userId,
                'assigned_to' => null,
                'status' => 'draft',
                'priority' => $validated['priority'] ?? 'normal',
                'order_date' => now()->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }
            auditLog($userId, 'create', Order::class, $order->id, null, $order->toArray());
            return $order;
        });
    }

    public function update(Request $request, Order $order, array $validated) : Order
    {
        return  DB::transaction(function () use ($request, $order, $validated) {
        $lockedOrder = Order::whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedOrder->load('items');

        $oldValues = $lockedOrder->toArray();
        $oldValues['items'] = $lockedOrder->items->toArray();

        // Once stock has been deducted, changing the order lines or financial/customer
        // values could make the order and inventory disagree. Use status/assignment
        // endpoints for those workflows instead.
        if ($lockedOrder->stock_deducted_at !== null) {
            $restrictedFields = ['customer_id', 'order_date', 'discount', 'tax', 'items'];
            foreach ($restrictedFields as $field) {
                if (array_key_exists($field, $validated)) {
                    throw ValidationException::withMessages([
                        $field => ['This field cannot be changed after stock has been deducted.'],
                    ]);
                }
            }
        }

        if (array_key_exists('customer_id', $validated)) {
            $lockedOrder->customer_id = $validated['customer_id'];
        }
        if (array_key_exists('priority', $validated)) {
            $lockedOrder->priority = $validated['priority'];
        }
        if (array_key_exists('order_date', $validated)) {
            $lockedOrder->order_date = $validated['order_date'];
        }

        $subtotal = (float) $lockedOrder->subtotal;
        if (array_key_exists('items', $validated)) {
            $subtotal = 0;
            $newItems = [];

            foreach ($validated['items'] as $itemInput) {
                $product = Product::where('id',$itemInput['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => ["Product {$product->name} is not active."],
                    ]);
                }

                $quantity = (int) $itemInput['quantity'];
                $lineTotal = round((float) $product->price * $quantity, 2);
                $subtotal += $lineTotal;

                $newItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $lineTotal,
                ];
            }

            // Replace order lines only after all submitted products have been validated.
            $lockedOrder->items()->delete();
            foreach ($newItems as $newItem) {
                $lockedOrder->items()->create($newItem);
            }
        }

        $discount = array_key_exists('discount', $validated)? (float) $validated['discount']: (float) $lockedOrder->discount;
        $tax = array_key_exists('tax', $validated)? (float) $validated['tax']: (float) $lockedOrder->tax;

        if ($discount > $subtotal) {
            throw ValidationException::withMessages([
                'discount' => ['Discount cannot exceed the subtotal.'],
            ]);
        }

        $total = round($subtotal - $discount + $tax, 2);
        if ($total < 0) {
            throw ValidationException::withMessages([
                'total' => ['Order total cannot be negative.'],
            ]);
        }

        $lockedOrder->subtotal = round($subtotal, 2);
        $lockedOrder->discount = $discount;
        $lockedOrder->tax = $tax;
        $lockedOrder->total = $total;
        $lockedOrder->save();

        $newValues = $lockedOrder->fresh()->toArray();
        $newValues['items'] = $lockedOrder->items()->get()->toArray();
        auditLog($request->user()->id,'update',Order::class,$lockedOrder->id,$oldValues,$newValues);

        return $lockedOrder;
    });

    }

    public function destroy(Request $request,Order $order ): void
    {
         DB::transaction(function () use ($request, $order) {
            $lockedOrder = Order::where('id',$order->id)
                                ->lockForUpdate()
                                ->firstOrFail();

            $lockedOrder->load('items');

            $oldValues = $lockedOrder->toArray();
            $oldValues['items'] = $lockedOrder->items->toArray();

            if ($lockedOrder->stock_deducted_at !== null && $lockedOrder->status !== 'completed') {
                foreach ($lockedOrder->items as $item) {
                    $product = Product::where('id',$item->product_id)
                                        ->lockForUpdate()
                                        ->first();

                    // The product may have been softdeleted since the order was created
                    if ($product) {
                        $product->increment('stock_quantity', $item->quantity);
                    }
                }

                $lockedOrder->stock_deducted_at = null;
                $lockedOrder->save();
            }

            auditLog($request->user()->id,'delete',Order::class,$lockedOrder->id,$oldValues,null);

            $lockedOrder->delete(); // softDelete.
        });
    }


    public function updateStatus(Request $request,Order $order,string $newStatus,array $statusFlow):Order{
        return DB::transaction(function () use ($request,$order,$newStatus,$statusFlow) {
        // Lock the order to prevent concurrent status transitions.
        $lockedOrder = Order::where('id', $order->id)
            ->lockForUpdate()
            ->firstOrFail();


        $oldStatus = $lockedOrder->status;

        if (!in_array(
            $newStatus,
            $statusFlow[$oldStatus] ?? [],
            true
        )) {
            throw ValidationException::withMessages([
                'status' => [
                    "Cannot change order status from {$oldStatus} to {$newStatus}.",
                ],
            ]);
        }

        // Deduct stock once when the order is confirmed.
        if ($oldStatus === 'pending' && $newStatus === 'confirmed') {
            if ($lockedOrder->stock_deducted_at !== null) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Stock has already been deducted for this order.',
                    ],
                ]);
            }

            foreach ($lockedOrder->items as $item) {
                $product = Product::where('id',$item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'stock' => [
                            "Product {$product->name} is not active.",
                        ],
                    ]);
                }

                if ($product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => [
                            "Insufficient stock for {$product->name}.",
                        ],
                    ]);
                }

                $product->decrement(
                    'stock_quantity',
                    $item->quantity
                );
            }

            $lockedOrder->stock_deducted_at = now();
        }

        // Restore stock if a confirmed order is cancelled.
        // Older orders without the marker are not restored.
        if (
            $newStatus === 'cancelled' &&
            $lockedOrder->stock_deducted_at !== null
        ) {
            foreach ($lockedOrder->items as $item) {
                $product = Product::where('id',$item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $product->increment('stock_quantity',$item->quantity);
            }
            $lockedOrder->stock_deducted_at = null;
        }
        $lockedOrder->status = $newStatus;
        $lockedOrder->save();
        OrderStatusHistory::create([
            'order_id' => $lockedOrder->id,
            'user_id' => $request->user()->id,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
        auditLog(
            $request->user()->id,
            'update_status',
            Order::class,
            $lockedOrder->id,
            ['status' => $oldStatus],
            ['status' => $newStatus]
        );
        return $lockedOrder;
    });
    }
}
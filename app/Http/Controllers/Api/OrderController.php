<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\OrderStatusHistory;
use App\Models\AuditLog;
use Carbon\Carbon;
use App\Notifications\OrderNotification;
use App\Services\OrderService;
use App\Services\DashbaordService;
class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    
    public function index(Request $request,OrderService $orderService)
    {
    $paginatedOrders = $orderService->index($request);
            return response()->json([
                'success' => true,
                'data' => $paginatedOrders,
            ]);
    }

    
private function authorizeOrderAccess(Request $request, Order $order): void
{
    $user = $request->user();

    $canViewAll = $user->role?->permissions()
        ->where('permissions.name', 'orders.view_all')
        ->exists() ?? false;

    if ($canViewAll) {
        return;
    }

    $canViewAssigned = $user->role?->permissions()
        ->where('permissions.name', 'orders.view_assigned')
        ->exists() ?? false;

    abort_unless(
        $canViewAssigned && (int) $order->assigned_to === (int) $user->id,
        403,
        'You can only access orders assigned to you.'
    );
}


    public function create()
    {
        //
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(OrderRequest $request,OrderService $orderService)
    {
        $validated = $request->validated();
        $order = $orderService->create($validated,$request->user()->id);
        $request->user()->notify(new OrderNotification('New order created successfully.', '/orders/' . $order->id));
        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'data' => $order->load(['customer', 'items']),
        ], 201);
        }

public function show(Request $request, Order $order)
{
    $this->authorizeOrderAccess($request, $order);
    $order->load(['customer', 'assigned', 'items']);
    return response()->json([
        'success' => true,
        'data' => $order,
    ]);
}

public function update(OrderRequest $request, Order $order,OrderService $orderService)
{
    $validated =  $request->validated();
    $this->authorizeOrderAccess($request, $order);
    $updatedOrder = $orderService->update($request,$order,$validated);
    return response()->json([
        'success' => true,
        'message' => 'Order updated successfully.',
        'data' => $updatedOrder->fresh()->load(['customer', 'assigned', 'items']),
    ]);
}

/**
 * Soft-delete an order. If stock was deducted for an unfinished order, restore it
 * in the same transaction so product and order data stay consistent.
 */
public function destroy(Request $request, Order $order,OrderService $orderService)
{
   $this->authorizeOrderAccess($request, $order);
   $orderService->destroy($request,$order);
    return response()->json([
        'success' => true,
        'message' => 'Order deleted successfully.',
    ]);
}


// update order status
       
public function updateStatus(Request $request, Order $order,OrderService $orderService)
{
    $validated = $request->validate([
        'status' => 'required|in:draft,pending,confirmed,processing,ready,completed,cancelled',
    
    ]);
    $this->authorizeOrderAccess($request, $order);

    $newStatus = $validated['status'];
    $statusFlow = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    $updatedOrder = $orderService->updateStatus($request,$order,$newStatus,$statusFlow);
    $updatedOrder->loadMissing(['assigned', 'customer']);
    $recipient = $updatedOrder->assigned;
    if ($recipient) {
        $recipient->notify(new OrderNotification('Order #' .$updatedOrder->id. ' status changed to ' .$updatedOrder->status. '.',
        '/orders/' . $updatedOrder->id));
    }
    return response()->json([
        'success' => true,
        'message' => 'Order status updated successfully.',
        'data' => $updatedOrder->fresh()->load([
            'customer',
            'items',
            'assigned',
            'statusHistories',
        ]),
    ]);
}

// assign order to user

public function assignOrder(Request $request, Order $order)
{
    $this->authorizeOrderAccess($request, $order);
    $validated = $request->validate([
        'assigned_to' => 'required|exists:users,id',
    ]);

    $user = User::with('role')->findOrFail($validated['assigned_to']);

    if (!$user->role || !in_array($user->role->name, ['Manager', 'Staff'], true)) {
        return response()->json([
            'success' => false,
            'message' => 'Orders can only be assigned to Managers or Staff.',
        ], 422);
    }
   DB::transaction(function () use ($order,$request,$user) {
        $oldAssignedTo = $order->assigned_to;
        $order->update(['assigned_to' => $user->id]);
        $user->notify(new OrderNotification('Order #' . $order->id . ' has been assigned to you.', '/orders/' . $order->id));
        auditLog($request->user()->id, 'assign', Order::class, $order->id, ['assigned_to' => $oldAssignedTo], ['assigned_to' => $user->id]);
    }); 
    return response()->json([
        'success' => true,
        'message' => 'Order assigned successfully.',
        'data' => $order->fresh()->load('assigned'),
    ]);
}
// get order status history
public function showStatusHistory(Request $request, Order $order)
{
    $this->authorizeOrderAccess($request, $order);
    $history = OrderStatusHistory::where('order_id', $order->id)
            ->with('user:id,name')
            ->latest()
            ->get();

    return response()->json([
        'success' => true,
        'data' => $history,
    ]);
}
// get order status history
// public function statusHistory(Order $order)
// {
//     $history = $order->statusHistories()->with('user:id,name')->latest()->get();

//     return response()->json([
//         'success' => true,
//         'data' => $history,
//     ]);
// }
// add order notes
public function addNotes(Request $request, Order $order)
{
    $this->authorizeOrderAccess($request, $order);
    $validated = $request->validate([
        'note' => 'required|string|max:1000',
    ]);

    $note = $order->notes()->create([
        'user_id' => $request->user()->id,
        'note' => $validated['note'],
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Note added successfully.',
        'data' => $note,
    ]);
}
// get order notes
public function getNotes(Request $request, Order $order)
{
    $this->authorizeOrderAccess($request, $order);
    $notes = $order->notes()->with('user:id,name')->latest()->get();

    return response()->json([
        'success' => true,
        'data' => $notes,
    ]);
}


// public function activity(Order $order)
// {
//     // dd($order->id);
//     $order->load(['statusHistories.user:id,name','notes.user:id,name',]);

//     $statusHistory = $order->statusHistories->map(function ($history) {
//         return [
//             'type' => 'status',
//             'action' => 'status_changed',
//             'user' => $history->user?->name,
//             'details' => [
//                 'old_status' => $history->previous_status,
//                 'new_status' => $history->new_status,
//             ],
//             'created_at' => $history->created_at,
//         ];
//     });

//     $notes = $order->notes->map(function ($note) {
//         return [
//             'type' => 'note',
//             'action' => 'note_added',
//             'user' => $note->user?->name,
//             'details' => [
//                 'note' => $note->note,
//             ],
//             'created_at' => $note->created_at,
//         ];
//     });

//     $auditLogs = AuditLog::where('model_type', Order::class)
//         ->where('model_id', $order->id)
//         ->whereNotIn('action', ['status_changed'])
//         ->with('user:id,name')
//         ->get()
//         ->map(function ($log) {
//             return [
//                 'type' => 'audit',
//                 'action' => $log->action,
//                 'user' => $log->user?->name,
//                 'details' => [
//                     'old_values' => $log->old_values,
//                     'new_values' => $log->new_values,
//                 ],
//                 'created_at' => $log->created_at,
//             ];
//         });

//     $activity = $statusHistory->concat($notes)->concat($auditLogs)->sortByDesc('created_at')->values();

//     return response()->json([
//         'success' => true,
//         'data' => $activity,
//     ]);
// }

public function activity(Request $request, Order $order)
{
    $this->authorizeOrderAccess($request, $order);
    $statusHistory = $order->statusHistories()
        ->get()
        ->map(function ($item) {
            return [
                'type' => 'status',
                'action' => 'status_changed',
                'description' => $item->note
                    ?? "Status changed to {$item->status}",
                'details' => [
                    'old_status' => $item->previous_status,
                    'new_status' => $item->new_status,
                    'note' => $item->note ?? null,
                ],

            'user' => $item->user?->name ?? 'System',
            'created_at' => $item->created_at
                ? Carbon::parse($item->created_at)->format('Y-m-d H:i:s')
                : null,
            ];
        });

    $notes = $order->notes()
        ->get()
        ->map(function ($item) {
            return [
                'type' => 'note',
                'action' => 'note_added',
                'description' => $item->note,
                'user' => $item->user->name ?? 'System',
                'created_at' => Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
            ];
        });

    $auditLogs = AuditLog::where('model_type', Order::class)
        ->where('model_id', $order->id)
        ->where('action', '!=', 'status_changed')
        ->get()
        ->map(function ($item) {
            return [
                'type' => 'audit',
                'action' => $item->action,
                'description' => $item->description
                    ?? ucfirst($item->action),
                'user' => $item->user->name ?? 'System',
                'created_at' => Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
            ];
        });
    $activity = $statusHistory->concat($notes)->concat($auditLogs)->sortByDesc('created_at')->values();
    return response()->json([
        'data' => $activity,
    ]);
}
public function dashboard(DashbaordService $dashbaordService)
{
    // $totalOrders = Order::count();
    // $activeOrders = Order::whereNotIn('status', ['draft','completed','cancelled'])->count();
    // $completedOrders = Order::where('status', 'completed')->count();
    // $cancelledOrders = Order::where('status', 'cancelled')->count();
    // $totalRevenue = Order::where('status', 'completed')->sum('total');
    // $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
    //                     ->groupBy('status')
    //                     ->get()
    //                     ->pluck('count', 'status');
    // $totalStatusCount = Order::select(DB::raw('count(*) as count'))
    //                     ->groupBy('status')
    //                     ->get()
    //                     ->sum('count');
    // $revenueLast6Months = Order::select(DB::raw('DATE_FORMAT(order_date, "%Y-%m") as month'), DB::raw('sum(total) as revenue'))
    //                     ->where('status', 'completed')
    //                     ->where('order_date', '>=', Carbon::now()->subMonths(6)->startOfMonth())
    //                     ->groupBy('month')
    //                     ->orderBy('month', 'asc')
    //                     ->get()
    //                     ->pluck('revenue', 'month');
    // $ordersByPriority = Order::select('priority', DB::raw('count(*) as count'))
    //                     ->groupBy('priority')
    //                     ->get()
    //                     ->pluck('count', 'priority');

    // return response()->json([
    //     'success' => true,
    //     'data' => [
    //         'total_orders' => $totalOrders,
    //         'orders_by_status' => $ordersByStatus,
    //         'orders_by_priority' => $ordersByPriority,
    //         'active_orders' => $activeOrders,
    //         'completed_orders' => $completedOrders,
    //         'cancelled_orders' => $cancelledOrders,
    //         'total_revenue' => $totalRevenue,
    //         'revenue_last_6_months' => $revenueLast6Months,
    //         'total_status_count' => $totalStatusCount,
    //     ],
    // ]);

   
    return response()->json([
        'success' => true,
        'data' => $dashbaordService->getDashboardData()
        
    ]);
}
}

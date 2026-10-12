<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashbaordService
{
    public function getDashboardData(): array
    {
    $totalOrders = Order::count();
    $activeOrders = Order::whereNotIn('status', ['draft','completed','cancelled'])->count();

    $completedOrders = Order::where('status', 'completed')->count();
    $cancelledOrders = Order::where('status', 'cancelled')->count();
    $totalRevenue = Order::where('status', 'completed')->sum('total');
    $ordersByStatus = Order::query()->select('status', DB::raw('COUNT(*) as count'))
                                    ->groupBy('status')
                                    ->pluck('count', 'status');
    $ordersByPriority = Order::query()->select('priority', DB::raw('COUNT(*) as count'))
                                      ->groupBy('priority')
                                      ->pluck('count', 'priority');
    $startDate = Carbon::now()->startOfMonth()->subMonths(5);
    // $monthlyRevenue = Order::query()
    //     ->select(
    //         DB::raw("DATE_FORMAT(order_date, '%Y-%m') as month"),
    //         DB::raw('SUM(total) as revenue')
    //     )
    //     ->where('status', 'completed')
    //     ->where('order_date', '>=', $startDate->toDateString())
    //     ->groupBy(DB::raw("DATE_FORMAT(order_date, '%Y-%m')"))
    //     ->orderBy('month')
    //     ->pluck('revenue', 'month');
    $monthlyRevenue = Order::query()
                ->select('order_date', 'total')
                ->where('status', 'completed')
                ->where('order_date', '>=', $startDate->toDateString())
                ->get()
                ->groupBy(function ($order) {
                    return Carbon::parse($order->order_date)->format('Y-m');
                })
                ->map(function ($orders) {
                    return $orders->sum('total');
                })
                ->sortKeys();

    // Fill months with zero revenue so the chart always has six months.
    $revenueLast6Months = [];

    for ($i = 0; $i<6; $i++) {
        $month = $startDate->copy()->addMonths($i)->format('Y-m');
        $revenueLast6Months[$month] = (float) (
            $monthlyRevenue[$month] ?? 0
        );
    }
     return [
            'total_orders' => $totalOrders,
            'orders_by_status' => $ordersByStatus,
            'orders_by_priority' => $ordersByPriority,
            'active_orders' => $activeOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_revenue' => (float) $totalRevenue,
            'revenue_last_6_months' => $revenueLast6Months,
            'total_status_count' => $ordersByStatus->sum(),
        ];
}
}
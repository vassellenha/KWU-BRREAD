<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $query = Order::with(['orderItems.component'])->latest();

        if ($status !== 'all' && in_array($status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'ilike', "%{$search}%")
                    ->orWhere('customer_name', 'ilike', "%{$search}%")
                    ->orWhere('customer_phone', 'ilike', "%{$search}%");
            });
        }

        $orders = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => Order::count(),
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'confirmed' => Order::where('status', Order::STATUS_CONFIRMED)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
            'avg_rating' => round((float) Order::whereNotNull('rating')->avg('rating'), 1),
            'reviews_count' => Order::whereNotNull('rating')->count(),
        ];

        return view('staff.orders.index', [
            'orders' => $orders,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    public function confirm(Order $order): RedirectResponse
    {
        if ($order->isCancelled()) {
            return back()->with('error', 'Pesanan yang sudah dibatalkan tidak dapat dikonfirmasi.');
        }

        if ($order->isConfirmed()) {
            return back()->with('info', "Pesanan #{$order->order_number} sudah terkonfirmasi sebelumnya.");
        }

        $token = Str::random(32);

        $order->update([
            'status' => Order::STATUS_CONFIRMED,
            'confirmation_token' => $token,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', "Pesanan #{$order->order_number} berhasil dikonfirmasi! Tautan tracking publik telah aktif.");
    }

    public function cancel(Order $order): RedirectResponse
    {
        if ($order->isCancelled()) {
            return back()->with('info', "Pesanan #{$order->order_number} sudah berstatus batal.");
        }

        DB::transaction(function () use ($order) {
            // Restore inventory for items in this order
            $order->loadMissing('orderItems');
            $tally = [];
            foreach ($order->orderItems as $item) {
                $tally[$item->component_id] = ($tally[$item->component_id] ?? 0) + $item->quantity;
            }

            foreach ($tally as $componentId => $quantity) {
                $component = Component::find($componentId);
                if ($component) {
                    $component->recordStockMovement(
                        type: StockMovement::TYPE_ADJUSTMENT,
                        quantityChange: $quantity,
                        userId: auth()->id(),
                        orderId: $order->id,
                        note: "Restorasi stok pembatalan pesanan #{$order->order_number}",
                    );
                }
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        });

        return back()->with('success', "Pesanan #{$order->order_number} berhasil dibatalkan dan stok bahan telah dipulihkan ke gudang.");
    }
}


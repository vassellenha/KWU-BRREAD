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

        if ($status !== 'all' && in_array($status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PAID, Order::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        } else {
            // "Semua Pesanan" leaves out cancelled orders; they live in their own tab.
            $status = 'all';
            $query->where('status', '!=', Order::STATUS_CANCELLED);
        }

        if ($search !== '') {
            // LOWER() + LIKE works on every driver (ILIKE is PostgreSQL only), and the user's
            // own % and _ are escaped so searching for "%" does not match every order.
            $needle = '%'.preg_replace('/[!%_]/', '!$0', Str::lower($search)).'%';
            $query->where(function ($q) use ($needle) {
                foreach (['order_number', 'customer_name', 'customer_phone'] as $column) {
                    $q->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$needle]);
                }
            });
        }

        $orders = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => Order::count(),
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'confirmed' => Order::where('status', Order::STATUS_CONFIRMED)->count(),
            'paid' => Order::where('status', Order::STATUS_PAID)->count(),
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
        $outcome = DB::transaction(function () use ($order) {
            // Re-read under a lock so two clicks (or two staff) cannot both act on the same order.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCancelled()) {
                return ['error', 'Pesanan yang sudah dibatalkan tidak dapat dikonfirmasi.'];
            }

            if ($order->isConfirmed()) {
                return ['info', "Pesanan #{$order->order_number} sudah terkonfirmasi sebelumnya."];
            }

            $order->update([
                'status' => Order::STATUS_CONFIRMED,
                'confirmation_token' => Str::random(32),
                'confirmed_at' => now(),
            ]);

            return ['success', "Pesanan #{$order->order_number} berhasil dikonfirmasi! Tautan tracking publik telah aktif."];
        });

        return back()->with(...$outcome);
    }

    public function markPaid(Order $order): RedirectResponse
    {
        $outcome = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCancelled()) {
                return ['error', 'Pesanan yang sudah dibatalkan tidak dapat ditandai dibayar.'];
            }

            if ($order->isPending()) {
                return ['error', "Konfirmasi pesanan #{$order->order_number} dulu sebelum menandainya dibayar."];
            }

            if ($order->isPaid()) {
                return ['info', "Pesanan #{$order->order_number} sudah ditandai dibayar sebelumnya."];
            }

            $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

            return ['success', "Pesanan #{$order->order_number} ditandai sudah dibayar. Halaman tracking pembeli ikut berubah."];
        });

        return back()->with(...$outcome);
    }

    public function cancel(Order $order): RedirectResponse
    {
        $outcome = DB::transaction(function () use ($order) {
            // The lock matters: without it a double click restores the stock twice.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCancelled()) {
                return ['info', "Pesanan #{$order->order_number} sudah berstatus batal."];
            }

            $tally = [];
            foreach ($order->orderItems as $item) {
                $tally[$item->component_id] = ($tally[$item->component_id] ?? 0) + $item->quantity;
            }

            foreach ($tally as $componentId => $quantity) {
                $component = Component::whereKey($componentId)->lockForUpdate()->first();
                $component?->recordStockMovement(
                    type: StockMovement::TYPE_ADJUSTMENT,
                    quantityChange: $quantity,
                    userId: auth()->id(),
                    orderId: $order->id,
                    note: "Restorasi stok pembatalan pesanan #{$order->order_number}",
                );
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return ['success', "Pesanan #{$order->order_number} berhasil dibatalkan dan stok bahan telah dipulihkan ke gudang."];
        });

        return back()->with(...$outcome);
    }
}

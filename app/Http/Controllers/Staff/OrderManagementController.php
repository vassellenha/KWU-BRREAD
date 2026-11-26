<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Employee;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\OrderEditor;
use App\Support\ContactInfo;
use App\Support\IngredientIcon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        // A pickup day (Y-m-d), or "none" for orders nobody has given a day yet; anything else shows every day.
        $pickup = (string) $request->query('pickup', '');
        $pickup = ($pickup === 'none' || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup) && strtotime($pickup) !== false)) ? $pickup : '';

        // The atmint the customer chatted with, or "none" for orders where nobody recorded it.
        $atmint = trim((string) $request->query('atmint', ''));
        $atmint = mb_substr($atmint, 0, 80);

        $query = Order::with(['orderItems.component', 'revisions.user'])->latest();

        if ($status === Order::STATUS_PENDING) {
            // "Masuk" holds every order that has not been paid yet: waiting for confirmation, or confirmed and waiting to pay.
            $query->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED]);
        } elseif ($status === 'unconfirmed') {
            // Not confirmed yet: no tracking link, nothing paid.
            $query->where('status', Order::STATUS_PENDING);
        } elseif (in_array($status, [Order::STATUS_CONFIRMED, Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_READY, Order::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        } else {
            // "Semua Pesanan" leaves out cancelled orders; they live in their own tab.
            $status = 'all';
            $query->where('status', '!=', Order::STATUS_CANCELLED);
        }

        if ($pickup === 'none') {
            $query->whereNull('pickup_on');
        } elseif ($pickup !== '') {
            $query->whereDate('pickup_on', $pickup);
        }

        if ($atmint === 'none') {
            $query->whereNull('chat_admin');
        } elseif ($atmint !== '') {
            $query->where('chat_admin', $atmint);
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
            'processing' => Order::where('status', Order::STATUS_PROCESSING)->count(),
            'ready' => Order::where('status', Order::STATUS_READY)->count(),
            'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
            'avg_rating' => round((float) Order::whereNotNull('rating')->avg('rating'), 1),
            'reviews_count' => Order::whereNotNull('rating')->count(),
        ];

        // Per pickup day (cancelled orders left out): how many orders and sandwiches to prepare, and for whom.
        $active = Order::with('orderItems')->where('status', '!=', Order::STATUS_CANCELLED)->get();
        $today = now()->toDateString();
        $pickupDays = $active->whereNotNull('pickup_on')
            ->groupBy(fn (Order $order) => $order->pickup_on->toDateString())
            ->filter(fn ($group, $date) => $date >= $today || $date === $pickup)
            ->sortKeys()
            ->map(fn ($group, $date) => [
                'date' => $date,
                'orders' => $group->count(),
                'sandwiches' => $group->sum(fn (Order $order) => $order->sandwichCount()),
                'people' => $group->map(fn (Order $order) => $order->customer_name.' ('.$order->sandwichCount().')')->all(),
            ])
            ->values();
        $unscheduled = $active->whereNull('pickup_on')->count();

        // Per atmint (cancelled orders left out): who holds how many orders. Names on the list come first.
        $atmintNames = collect(ContactInfo::get()['whatsapp_links'])->pluck('name')->unique()->values();
        $atmintChips = $active->whereNotNull('chat_admin')
            ->groupBy('chat_admin')
            ->map(fn ($group, $name) => ['name' => (string) $name, 'orders' => $group->count(), 'sandwiches' => $group->sum(fn (Order $order) => $order->sandwichCount())])
            ->sortByDesc('orders')
            ->values();
        $atmintUnset = $active->whereNull('chat_admin')->count();

        return view('staff.orders.index', [
            'pickup' => $pickup,
            'atmint' => $atmint,
            'atmintNames' => $atmintNames,
            'hrNames' => $this->hrNames(),
            'atmintChips' => $atmintChips,
            'atmintUnset' => $atmintUnset,
            'pickupDays' => $pickupDays,
            'unscheduled' => $unscheduled,
            'orders' => $orders,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $search,
            'editCatalog' => Component::orderBy('category')->orderBy('name')->get()->map(fn (Component $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'category' => $c->category,
                'price' => (float) $c->price,
                'stock' => (int) $c->stock_quantity,
                'active' => (bool) $c->is_active,
                'icon' => IngredientIcon::for($c->name),
            ])->values()->all(),
            'editor' => app(OrderEditor::class),
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

            if ($order->hasBeenPaid()) {
                return ['info', "Pesanan #{$order->order_number} sudah ditandai dibayar sebelumnya."];
            }

            $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

            return ['success', "Pesanan #{$order->order_number} ditandai sudah dibayar. Halaman tracking pembeli ikut berubah."];
        });

        return back()->with(...$outcome);
    }

    public function markPreparing(Order $order): RedirectResponse
    {
        $outcome = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCancelled()) {
                return ['error', 'Pesanan yang sudah dibatalkan tidak dapat disiapkan.'];
            }

            if (! $order->hasBeenPaid()) {
                return ['error', "Catat pembayaran pesanan #{$order->order_number} dulu sebelum menyiapkannya."];
            }

            if ($order->hasBeenPrepared()) {
                return ['info', "Pesanan #{$order->order_number} sudah ditandai disiapkan sebelumnya."];
            }

            $order->update(['status' => Order::STATUS_PROCESSING, 'preparing_at' => now()]);

            return ['success', "Pesanan #{$order->order_number} ditandai sedang disiapkan. Halaman tracking pembeli ikut berubah."];
        });

        return back()->with(...$outcome);
    }

    public function markReady(Order $order): RedirectResponse
    {
        $outcome = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCancelled()) {
                return ['error', 'Pesanan yang sudah dibatalkan tidak dapat ditandai siap diambil.'];
            }

            if ($order->isReady()) {
                return ['info', "Pesanan #{$order->order_number} sudah ditandai siap diambil sebelumnya."];
            }

            if (! $order->isProcessing()) {
                return ['error', "Tandai pesanan #{$order->order_number} sedang disiapkan dulu sebelum siap diambil."];
            }

            $order->update(['status' => Order::STATUS_READY, 'ready_at' => now()]);

            return ['success', "Pesanan #{$order->order_number} ditandai siap diambil. Halaman tracking pembeli ikut berubah."];
        });

        return back()->with(...$outcome);
    }

    /**
     * The day the customer can collect the order (a market day, say). It shows on the customer's tracking page;
     * saving an empty date takes it off again. Saving bumps updated_at, so an open tracking page notices the change.
     */
    public function setPickupDate(Request $request, Order $order): RedirectResponse
    {
        if ($order->isCancelled()) {
            return back()->with('error', 'Pesanan yang sudah dibatalkan tidak perlu tanggal pengambilan.');
        }

        $validator = Validator::make($request->all(), [
            'pickup_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before:'.now()->addYear()->toDateString()],
        ], [
            'pickup_on.date_format' => 'Tanggal pengambilan tidak valid.',
            'pickup_on.after_or_equal' => 'Tanggal pengambilan tidak boleh sebelum hari ini.',
            'pickup_on.before' => 'Tanggal pengambilan maksimal satu tahun dari sekarang.',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $date = $validator->validated()['pickup_on'] ?? null;

        if ($date === null) {
            $order->update(['pickup_on' => null]);

            return back()->with('success', "Tanggal pengambilan pesanan #{$order->order_number} dihapus.");
        }

        $order->update(['pickup_on' => $date]);

        return back()->with('success', "Pesanan #{$order->order_number} bisa diambil pada ".$order->pickup_on->locale('id')->translatedFormat('l, j F Y').'. Halaman tracking pembeli ikut berubah.');
    }

    /**
     * The active people in HR, in rotation order: who can be credited with an order as sales or marketing.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function hrNames(): \Illuminate\Support\Collection
    {
        return Employee::where('is_active', true)->orderBy('rotation_order')->orderBy('name')->pluck('name')->unique()->values();
    }

    public function setAttribution(Request $request, Order $order): RedirectResponse
    {
        $names = collect(ContactInfo::get()['whatsapp_links'])->pluck('name')->push($order->chat_admin)->filter()->unique()->values()->all();

        // Sales or marketing is picked from the people in HR (and the atmints), never typed in.
        $salesPeople = $this->hrNames()->concat($names)->push($order->sold_by)->filter()->unique()->values()->all();

        $validator = Validator::make($request->all(), [
            'chat_admin' => ['nullable', 'string', 'max:80', Rule::in($names)],
            'sold_by' => ['nullable', 'string', 'max:80', Rule::in($salesPeople)],
        ], [
            'chat_admin.in' => 'Atmint tersebut tidak ada di daftar kontak WhatsApp.',
            'sold_by.in' => 'Sales/marketing harus dipilih dari daftar tim di menu HR.',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $data = $validator->validated();
        $order->update([
            'chat_admin' => ($data['chat_admin'] ?? '') !== '' ? $data['chat_admin'] : null,
            'sold_by' => trim((string) ($data['sold_by'] ?? '')) !== '' ? trim($data['sold_by']) : null,
        ]);

        return back()->with('success', "Atmint dan sales pesanan #{$order->order_number} disimpan.");
    }

    public function cancel(Order $order): RedirectResponse
    {
        $outcome = DB::transaction(function () use ($order) {
            // The lock matters: without it a double click restores the stock twice.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCancelled()) {
                return ['info', "Pesanan #{$order->order_number} sudah berstatus batal."];
            }

            if ($order->isReady()) {
                return ['error', "Pesanan #{$order->order_number} sudah siap diambil, jadi tidak bisa dibatalkan."];
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

    /**
     * Permanently removes an order, for dummy or test orders that should not sit in the reports.
     * Stock the order still holds is returned first; a cancelled order already gave it back.
     */
    public function destroy(Order $order): RedirectResponse
    {
        $outcome = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $number = $order->order_number;

            if (! $order->isCancelled()) {
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
                        note: "Restorasi stok penghapusan pesanan #{$number}",
                    );
                }
            }

            // Order items and the review photo go with it (cascade); stock history keeps its rows.
            $order->delete();

            return ['success', "Pesanan #{$number} dihapus permanen dan stok bahan dipulihkan."];
        });

        return back()->with(...$outcome);
    }
}

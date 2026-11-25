<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRevision;
use App\Models\StockMovement;
use App\Support\OrderChangeSummary;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff edits of an order that is already in: swap bread, flavor or toppings (and the quantity) per creation, for
 * example when the customer's flavor ran out and the admin agreed on another one over WhatsApp.
 *
 * Stock is taken when the order is placed, so an edit only moves the difference (one adjustment per ingredient that
 * changed). Ingredients a creation keeps stay at the price they were ordered at; new ones use today's menu price.
 */
class OrderEditor
{
    /** Past these the sandwich is being made or is done, or the order is gone. */
    public const EDITABLE = [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PAID];

    public static function canEdit(Order $order): bool
    {
        return in_array($order->status, self::EDITABLE, true);
    }

    /**
     * The creations as names, in the shape stored in order_revisions.
     *
     * @return list<array{index: int, creation: int, quantity: int, bread: string, flavor: string, toppings: list<string>}>
     */
    public function snapshot(Order $order): array
    {
        $order->loadMissing('orderItems.component');
        $position = 0;

        return $order->orderItems
            ->sortBy('layer_order')
            ->groupBy('creation_index')
            ->sortKeys()
            ->map(function (Collection $items, $index) use (&$position) {
                $position++;

                return [
                    'index' => (int) $index,
                    'creation' => $position,
                    'quantity' => (int) ($items->first()?->quantity ?? 1),
                    'bread' => $items->first(fn (OrderItem $i) => $i->component?->category === 'bread')?->component?->name ?? '-',
                    'flavor' => $items->first(fn (OrderItem $i) => $i->component?->category === 'ice_cream')?->component?->name ?? '-',
                    'toppings' => $items->filter(fn (OrderItem $i) => $i->component?->category === 'topping')
                        ->map(fn (OrderItem $i) => $i->component->name)->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** What the editor form was opened on: any change to the order, its status or its items changes this. */
    public function version(Order $order): string
    {
        $items = $order->orderItems()->orderBy('id')->get(['component_id', 'creation_index', 'quantity', 'unit_price'])
            ->map(fn ($i) => "{$i->creation_index}:{$i->component_id}:{$i->quantity}:{$i->unit_price}")
            ->implode(',');

        return substr(sha1($order->id.'|'.$order->status.'|'.$order->total_price.'|'.$order->updated_at?->format('Y-m-d H:i:s.u').'|'.$items), 0, 16);
    }

    /**
     * What saving these creations would do, without saving anything.
     *
     * @param  list<array{index: int, component_ids: list<int>, quantity: int}>  $input
     * @return array{rows: list<array<string, mixed>>, before: array, after: array, total_before: float, total_after: float, difference: float, changes: list<string>, deltas: array<int, int>, components: Collection}
     */
    public function plan(Order $order, array $input, bool $lock = false): array
    {
        if (! self::canEdit($order)) {
            throw ValidationException::withMessages([
                'items' => 'Pesanan ini sudah '.($order->isCancelled() ? 'dibatalkan' : 'disiapkan atau siap diambil').', jadi isinya tidak bisa diubah lagi.',
            ]);
        }

        $before = $this->snapshot($order);
        $existing = $order->orderItems->groupBy('creation_index');

        $wanted = collect($input)->keyBy(fn (array $row) => (int) $row['index']);
        if ($wanted->count() !== count($input) || $wanted->keys()->sort()->values()->all() !== $existing->keys()->map(fn ($k) => (int) $k)->sort()->values()->all()) {
            throw ValidationException::withMessages(['items' => 'Daftar kreasi tidak cocok dengan pesanan ini. Muat ulang halaman lalu coba lagi.']);
        }

        $ids = collect($input)->pluck('component_ids')->flatten()->merge($order->orderItems->pluck('component_id'))->unique()->values()->all();
        $query = Component::whereIn('id', $ids);
        $components = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');

        $rows = [];
        $after = [];
        $position = 0;
        $errors = [];

        foreach ($existing->sortKeys() as $index => $oldItems) {
            $position++;
            $request = $wanted->get((int) $index);
            $componentIds = array_map('intval', $request['component_ids']);

            try {
                OrderCreationRules::ensureComplete($componentIds, $components, $position);
            } catch (ValidationException $e) {
                $errors[] = $e->errors()['items'][0];

                continue;
            }

            $oldPrices = $oldItems->pluck('unit_price', 'component_id');

            // Bread first, then the flavor, then the toppings in the order they were given.
            $ordered = collect($componentIds)->sortBy(fn (int $id) => ['bread' => 0, 'ice_cream' => 1][$components[$id]->category] ?? 2)->values();

            foreach ($ordered as $layer => $id) {
                $component = $components[$id];
                $isNew = ! $oldPrices->has($id);

                if ($isNew && ! $component->is_active) {
                    $errors[] = "{$component->name} sudah tidak aktif di menu, jadi tidak bisa dipakai.";
                }

                $rows[] = [
                    'component_id' => $id,
                    'creation_index' => (int) $index,
                    'layer_order' => $layer,
                    'quantity' => (int) $request['quantity'],
                    'unit_price' => $isNew ? $component->price : $oldPrices[$id],
                ];
            }

            $after[] = [
                'index' => (int) $index,
                'creation' => $position,
                'quantity' => (int) $request['quantity'],
                'bread' => $components[$ordered->first(fn ($id) => $components[$id]->category === 'bread')]->name,
                'flavor' => $components[$ordered->first(fn ($id) => $components[$id]->category === 'ice_cream')]->name,
                'toppings' => $ordered->filter(fn ($id) => $components[$id]->category === 'topping')->map(fn ($id) => $components[$id]->name)->values()->all(),
            ];
        }

        // How many units of each ingredient the order holds now and would hold after: the difference is what stock moves.
        $tally = fn (iterable $items) => collect($items)->groupBy('component_id')->map(fn ($group) => (int) $group->sum('quantity'));
        $oldTally = $tally($order->orderItems->map(fn ($i) => ['component_id' => $i->component_id, 'quantity' => $i->quantity]));
        $newTally = $tally($rows);

        $deltas = [];
        foreach ($oldTally->keys()->merge($newTally->keys())->unique() as $componentId) {
            $delta = ($newTally[$componentId] ?? 0) - ($oldTally[$componentId] ?? 0);
            if ($delta !== 0) {
                $deltas[(int) $componentId] = $delta;
            }
        }

        if ($errors === []) {
            foreach ($deltas as $componentId => $delta) {
                $component = $components->get($componentId) ?? Component::find($componentId);
                if ($delta > 0 && $component !== null && $component->stock_quantity < $delta) {
                    $errors[] = $component->stock_quantity > 0
                        ? "Stok {$component->name} tinggal {$component->stock_quantity}, padahal perubahan ini butuh tambahan {$delta}."
                        : "Stok {$component->name} habis, jadi tidak bisa ditambah.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['items' => $errors]);
        }

        $totalAfter = collect($rows)->sum(fn ($row) => (float) $row['unit_price'] * $row['quantity']);

        return [
            'rows' => $rows,
            'before' => $before,
            'after' => $after,
            'total_before' => (float) $order->total_price,
            'total_after' => (float) $totalAfter,
            'difference' => (float) $totalAfter - (float) $order->total_price,
            'changes' => OrderChangeSummary::lines($before, $after),
            'deltas' => $deltas,
            'components' => $components,
        ];
    }

    /**
     * Saves the edit atomically: items, total, stock movements and one revision row.
     *
     * @param  list<array{index: int, component_ids: list<int>, quantity: int}>  $input
     */
    public function apply(Order $order, array $input, string $version, string $reason, ?int $userId): OrderRevision
    {
        return DB::transaction(function () use ($order, $input, $version, $reason, $userId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! hash_equals($this->version($order), $version)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Pesanan ini berubah sejak form dibuka (mungkin oleh admin lain). Muat ulang halaman lalu ulangi perubahannya.',
                    'conflict' => true,
                ], 409));
            }

            $plan = $this->plan($order, $input, lock: true);

            if ($plan['changes'] === [] && abs($plan['difference']) < 0.005) {
                throw ValidationException::withMessages(['items' => 'Belum ada yang berubah dari pesanan aslinya.']);
            }

            foreach ($plan['deltas'] as $componentId => $delta) {
                $component = $plan['components']->get($componentId) ?? Component::whereKey($componentId)->lockForUpdate()->first();
                $component?->recordStockMovement(
                    type: StockMovement::TYPE_ADJUSTMENT,
                    quantityChange: -$delta,
                    userId: $userId,
                    orderId: $order->id,
                    note: "Edit pesanan #{$order->order_number}: {$reason}",
                );
            }

            $order->orderItems()->delete();
            foreach ($plan['rows'] as $row) {
                $order->orderItems()->create($row);
            }

            $order->forceFill(['total_price' => $plan['total_after']])->save();
            $order->touch();
            $order->unsetRelation('orderItems');

            return $order->revisions()->create([
                'user_id' => $userId,
                'reason' => $reason,
                'before' => $plan['before'],
                'after' => $plan['after'],
                'total_before' => $plan['total_before'],
                'total_after' => $plan['total_after'],
            ]);
        });
    }

    /**
     * What the admin's edit dialog is opened with: the creations as ingredient ids plus the order's history.
     *
     * @return array<string, mixed>
     */
    public function editorPayload(Order $order): array
    {
        $order->loadMissing('orderItems', 'revisions.user');

        return [
            'id' => $order->id,
            'number' => $order->order_number,
            'customer' => $order->customer_name,
            'can_edit' => self::canEdit($order),
            'paid' => $order->hasBeenPaid(),
            'version' => $this->version($order),
            'total' => (float) $order->total_price,
            'creations' => $order->orderItems->sortBy('layer_order')->groupBy('creation_index')->sortKeys()
                ->map(fn (Collection $items, $index) => [
                    'index' => (int) $index,
                    'quantity' => (int) ($items->first()?->quantity ?? 1),
                    'component_ids' => $items->pluck('component_id')->map(fn ($id) => (int) $id)->values()->all(),
                ])->values()->all(),
            'history' => $order->revisions->sortByDesc('id')->values()->map(fn (OrderRevision $revision) => $this->historyRow($order, $revision))->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function historyRow(Order $order, OrderRevision $revision): array
    {
        return [
            'at' => $revision->created_at->timezone(config('app.timezone'))->format('d M Y H:i'),
            'by' => $revision->user?->name ?? 'Admin',
            'reason' => $revision->reason,
            'changes' => $revision->changes(),
            'total_before' => (float) $revision->total_before,
            'total_after' => (float) $revision->total_after,
            'whatsapp_url' => $this->whatsappUrl($order, $revision),
        ];
    }

    /** A ready-to-send WhatsApp message to the customer about this edit. It only opens the chat; nothing is sent. */
    public function whatsappUrl(Order $order, OrderRevision $revision): ?string
    {
        $phone = $order->whatsappPhone();
        if ($phone === '') {
            return null;
        }

        $money = fn (float $amount): string => 'Rp '.number_format($amount, 0, ',', '.');
        $difference = $revision->difference();

        $lines = ["Halo Kak {$order->customer_name}, ini dari Brread!", '', "Pesanan #{$order->order_number} kamu kami ubah ya:"];
        foreach ($revision->changes() as $change) {
            $lines[] = "- {$change}";
        }
        $lines[] = '';
        $lines[] = "Alasan: {$revision->reason}";
        $lines[] = '';
        $lines[] = 'Total sebelumnya: '.$money((float) $revision->total_before);
        $lines[] = 'Total sekarang: '.$money((float) $revision->total_after).match (true) {
            $difference > 0 => ' (naik '.$money($difference).')',
            $difference < 0 => ' (turun '.$money(abs($difference)).')',
            default => ' (tetap)',
        };

        if ($order->public_status_url) {
            $lines[] = '';
            $lines[] = 'Cek isi pesananmu di sini:';
            $lines[] = $order->public_status_url;
        }

        $lines[] = '';
        $lines[] = 'Kalau ada yang kurang pas, kabari kami di chat ini ya. Terima kasih!';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode(implode("\n", $lines));
    }
}

<?php

namespace App\Models;

use App\Support\RowVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'price',
        'hex_color',
        'is_active',
        'stock_quantity',
        'unit',
        'reorder_point',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'stock_quantity' => 'integer',
        'reorder_point' => 'integer',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Fingerprint of the stock level, for the stock page's "save all" (see App\Support\RowVersion). */
    public function stockVersion(): string
    {
        return RowVersion::of($this, ['stock_quantity']);
    }

    /** Fingerprint of what the menu page edits. */
    public function menuVersion(): string
    {
        return RowVersion::of($this, ['name', 'price', 'hex_color', 'is_active']);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->reorder_point;
    }

    /**
     * Apply a signed quantity change and record the movement atomically.
     */
    public function recordStockMovement(string $type, int $quantityChange, ?int $userId = null, ?int $orderId = null, ?string $note = null): StockMovement
    {
        $this->stock_quantity = max(0, $this->stock_quantity + $quantityChange);
        $this->save();

        return $this->stockMovements()->create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'balance_after' => $this->stock_quantity,
            'note' => $note,
        ]);
    }
}

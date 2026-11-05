<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_RESTOCK = 'restock';

    public const TYPE_CONSUMPTION = 'consumption';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'component_id',
        'order_id',
        'user_id',
        'type',
        'quantity_change',
        'balance_after',
        'note',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use App\Support\OrderChangeSummary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved staff edit of an order. before / after hold the creations as names (see OrderEditor::snapshot()).
 */
class OrderRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'before',
        'after',
        'total_before',
        'total_after',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'total_before' => 'decimal:2',
        'total_after' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What changed, one friendly Indonesian line each ("Rasa Neapolitan diganti Matcha"). */
    public function changes(): array
    {
        return OrderChangeSummary::lines($this->before ?? [], $this->after ?? []);
    }

    /** Positive when the order got more expensive. */
    public function difference(): float
    {
        return (float) $this->total_after - (float) $this->total_before;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'total_price',
        'status',
        'confirmation_token',
        'confirmed_at',
        'cancelled_at',
        'customer_name',
        'customer_phone',
        'rating',
        'review_text',
        'reviewed_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function hasReview(): bool
    {
        return $this->rating !== null;
    }

    public function getPublicStatusUrlAttribute(): ?string
    {
        if (! $this->confirmation_token) {
            return null;
        }

        return route('order.status', ['token' => $this->confirmation_token]);
    }

    public function getWhatsappUrlAttribute(): string
    {
        $rawPhone = preg_replace('/[^0-9]/', '', $this->customer_phone);

        if (str_starts_with($rawPhone, '0')) {
            $rawPhone = '62'.substr($rawPhone, 1);
        } elseif (str_starts_with($rawPhone, '8')) {
            $rawPhone = '62'.$rawPhone;
        }

        $formattedTotal = 'Rp '.number_format((float) $this->total_price, 0, ',', '.');

        $message = "Halo Kak {$this->customer_name}! Terima kasih telah memesan di Brread! 🍦🥪\n\n"
            ."Kami ingin mengonfirmasi pesanan Anda:\n"
            ."• No. Pesanan: #{$this->order_number}\n"
            ."• Total: {$formattedTotal}\n\n"
            ."Apakah pesanan ini sudah benar dan siap kami proses?";

        return 'https://wa.me/'.$rawPhone.'?text='.rawurlencode($message);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PAID = 'paid';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'total_price',
        'status',
        'confirmation_token',
        'confirmed_at',
        'paid_at',
        'cancelled_at',
        'customer_name',
        'customer_phone',
        'rating',
        'review_text',
        'reviewed_at',
        'review_hidden_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_hidden_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** The photo the customer added to their review, if any. */
    public function reviewPhoto(): HasOne
    {
        return $this->hasOne(ReviewPhoto::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** Past the confirm step: confirmed and waiting for payment, or already paid. */
    public function hasBeenConfirmed(): bool
    {
        return $this->isConfirmed() || $this->isPaid();
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

        // Plain text only (no emoji: they arrive as a broken symbol on some phones). wa.me cannot attach a file, so
        // the shop's QRIS (public/img/qris.jpg) goes in as a link and WhatsApp shows it as a preview under the message.
        $message = "Halo Kak {$this->customer_name}! Terima kasih telah memesan di Brread!\n\n"
            ."Kami ingin mengonfirmasi pesanan Anda:\n"
            ."- No. Pesanan: #{$this->order_number}\n"
            ."- Total: {$formattedTotal}\n\n"
            ."Apakah pesanan ini sudah benar dan siap kami proses?\n\n"
            ."Jika sudah benar, silakan bayar {$formattedTotal} dengan scan QRIS berikut (nominal harus sesuai), lalu kirim bukti pembayarannya di chat ini:\n\n"
            .asset('img/qris.jpg');

        return 'https://wa.me/'.$rawPhone.'?text='.rawurlencode($message);
    }
}

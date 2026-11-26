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

    public const STATUS_READY = 'ready';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'total_price',
        'status',
        'confirmation_token',
        'confirmed_at',
        'paid_at',
        'preparing_at',
        'ready_at',
        'pickup_on',
        'cancelled_at',
        'customer_name',
        'customer_phone',
        'chat_admin',
        'sold_by',
        'rating',
        'review_text',
        'reviewed_at',
        'review_hidden_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'pickup_on' => 'date',
        'cancelled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_hidden_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Every staff edit of this order, oldest first (see App\Services\OrderEditor). */
    public function revisions(): HasMany
    {
        return $this->hasMany(OrderRevision::class);
    }

    /** The photo the customer added to their review, if any. */
    public function reviewPhoto(): HasOne
    {
        return $this->hasOne(ReviewPhoto::class);
    }

    /** How many sandwiches the order holds: each creation counts once per its quantity, however many ingredients it has. */
    public function sandwichCount(): int
    {
        return (int) $this->orderItems
            ->groupBy('creation_index')
            ->sum(fn ($items) => (int) ($items->first()?->quantity ?? 1));
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

    /** Staff are preparing the sandwich ("Pesanan disiapkan"). */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /** The last step: the order is ready to be picked up at the counter ("Pesanan siap diambil"). */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /** Paid, or past paid (being prepared, ready): the money is in either way. */
    public function hasBeenPaid(): bool
    {
        return $this->isPaid() || $this->hasBeenPrepared();
    }

    /** Being prepared, or ready for pickup. */
    public function hasBeenPrepared(): bool
    {
        return $this->isProcessing() || $this->isReady();
    }

    /** Past the confirm step: confirmed and waiting for payment, or any later step. */
    public function hasBeenConfirmed(): bool
    {
        return $this->isConfirmed() || $this->hasBeenPaid();
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

    /** The customer's number in international form for wa.me (62...), or '' when there is none. */
    public function whatsappPhone(): string
    {
        $rawPhone = preg_replace('/[^0-9]/', '', (string) $this->customer_phone);

        if (str_starts_with($rawPhone, '0')) {
            $rawPhone = '62'.substr($rawPhone, 1);
        } elseif (str_starts_with($rawPhone, '8')) {
            $rawPhone = '62'.$rawPhone;
        }

        return $rawPhone;
    }

    public function getWhatsappUrlAttribute(): string
    {
        $rawPhone = $this->whatsappPhone();

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

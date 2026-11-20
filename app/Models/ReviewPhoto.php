<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The photo that came with a customer's review, already downscaled and re-encoded (see App\Support\ReviewImage).
 * The bytes live in `data` as base64; the page reads them through the review.photo route, never inline.
 */
class ReviewPhoto extends Model
{
    protected $fillable = ['order_id', 'mime', 'width', 'height', 'data'];

    protected $hidden = ['data'];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The public address of the picture; the timestamp in it lets the browser cache it for good. */
    public function url(): string
    {
        return route('review.photo', ['photo' => $this->id, 'v' => $this->updated_at?->timestamp ?? 0]);
    }
}

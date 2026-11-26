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
    /** The shape of the public frame (width / height): 4 to 5. */
    public const FRAME_RATIO = 0.8;

    protected $fillable = ['order_id', 'mime', 'width', 'height', 'data', 'fit', 'focus_x', 'focus_y', 'zoom'];

    protected $hidden = ['data'];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'focus_x' => 'integer',
            'focus_y' => 'integer',
            'zoom' => 'integer',
        ];
    }

    /** "cover" fills the frame, "contain" shows the whole photo. Auto fills when the photo is near the frame's shape. */
    public function resolvedFit(): string
    {
        $fit = $this->fit ?? 'auto';
        if ($fit !== 'auto') {
            return $fit;
        }

        if (! $this->width || ! $this->height) {
            return 'cover';
        }

        return abs(($this->width / $this->height) / self::FRAME_RATIO - 1) <= 0.3 ? 'cover' : 'contain';
    }

    /** The CSS variables the review frame reads (see .review-frame in app.css). */
    public function frameStyle(): string
    {
        return sprintf(
            '--fit:%s;--fx:%d%%;--fy:%d%%;--zoom:%s',
            $this->resolvedFit(),
            $this->focus_x ?? 50,
            $this->focus_y ?? 50,
            rtrim(rtrim(number_format(($this->zoom ?? 100) / 100, 2, '.', ''), '0'), '.'),
        );
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

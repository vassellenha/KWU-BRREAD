<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The photo of a signature combo card, already downscaled and re-encoded (see App\Support\ReviewImage).
 * The bytes live in `data` as base64; the page reads them through the signature.photo route.
 */
class SignatureComboPhoto extends Model
{
    protected $fillable = ['signature_combo_id', 'mime', 'width', 'height', 'data'];

    protected $hidden = ['data'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(SignatureCombo::class, 'signature_combo_id');
    }

    /** The public address of the picture; the timestamp in it lets the browser cache it for good. */
    public function url(): string
    {
        return route('signature.photo', ['photo' => $this->id, 'v' => $this->updated_at?->timestamp ?? 0]);
    }
}

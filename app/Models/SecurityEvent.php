<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    public const REASONS = [
        'payload' => 'Attack payload',
        'throttle' => 'Too many attempts',
        'honeypot' => 'Orang Bego yang Kejebak',
    ];

    protected $fillable = [
        'reason', 'ip', 'username', 'path', 'user_agent', 'browser', 'os', 'device',
        'country', 'country_code', 'city', 'isp', 'hits', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function place(): ?string
    {
        $parts = array_filter([$this->city, $this->country]);

        return $parts ? implode(', ', $parts) : null;
    }
}

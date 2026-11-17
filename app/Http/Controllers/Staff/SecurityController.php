<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        $reason = $request->query('reason');
        $reason = is_string($reason) && array_key_exists($reason, SecurityEvent::REASONS) ? $reason : null;

        $events = SecurityEvent::query()
            ->when($reason, fn ($query) => $query->where('reason', $reason))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $week = SecurityEvent::where('created_at', '>=', now()->subDays(7));

        $topCountry = SecurityEvent::query()
            ->whereNotNull('country')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('country, country_code, count(*) as total')
            ->groupBy('country', 'country_code')
            ->orderByDesc('total')
            ->first();

        return view('staff.security.index', [
            'events' => $events,
            'reason' => $reason,
            'stats' => [
                'events' => (clone $week)->count(),
                'attempts' => (int) (clone $week)->sum('hits'),
                'addresses' => (clone $week)->distinct()->count('ip'),
                'top_country' => $topCountry,
            ],
            'keepDays' => (int) config('services.security_events.keep_days'),
        ]);
    }
}

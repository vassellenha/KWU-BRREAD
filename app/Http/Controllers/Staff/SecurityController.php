<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\IpBlock;
use App\Models\SecurityEvent;
use Illuminate\Http\RedirectResponse;
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
            'blocks' => IpBlock::with('blocker')->latest('id')->get(),
        ]);
    }

    /**
     * Shuts an address out of the whole site until it is unblocked.
     */
    public function block(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        if ($data['ip'] === $request->ip()) {
            return back()->withErrors(['ip' => 'That is the address you are using right now, so blocking it would lock you out too.']);
        }

        IpBlock::firstOrCreate(
            ['ip' => $data['ip']],
            ['note' => $data['note'] ?? null, 'blocked_by' => $request->user()->id],
        );

        return back()->with('status', "{$data['ip']} is blocked from the whole site.");
    }

    public function unblock(IpBlock $ipBlock): RedirectResponse
    {
        $ipBlock->delete();

        return back()->with('status', "{$ipBlock->ip} is unblocked.");
    }

    /**
     * Deletes every lockdown record for an address. A block on it stays until lifted.
     */
    public function forget(Request $request): RedirectResponse
    {
        $data = $request->validate(['ip' => ['required', 'ip']]);

        $count = SecurityEvent::where('ip', $data['ip'])->delete();

        return back()->with('status', "Deleted {$count} ".str('record')->plural($count)." for {$data['ip']}.");
    }
}

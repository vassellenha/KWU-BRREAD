<?php

namespace App\Http\Controllers;

use App\Support\HoneypotTrap;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HoneypotController extends Controller
{
    /**
     * Every honeypot address ends up here: the trap for a guest, nothing at all otherwise.
     */
    public function __invoke(Request $request): Response
    {
        if (! HoneypotTrap::enabled() || HoneypotTrap::exempt($request)) {
            abort(404, headers: ['Cache-Control' => 'no-store']);
        }

        // Signed in: never trapped, and there is no admin panel here either. The page only clears any old hold and goes Home.
        if ($request->user()) {
            return response(view('honeypot.released'))->header('Cache-Control', 'no-store');
        }

        $elapsed = HoneypotTrap::elapsed($request);

        // No hold yet: this visit starts one, unless it is only the browser resuming a hold that is over.
        // "?replay=1" (never in production) starts a fresh one even inside the grace period.
        $replay = $elapsed === null && HoneypotTrap::replayAllowed($request);

        if ($elapsed === null && ($replay || ! $request->boolean('resume')) && HoneypotTrap::begin($request, $replay)) {
            $elapsed = HoneypotTrap::elapsed($request);
        }

        // Over, or left alone: the page clears the browser's copy of the hold and goes to Home.
        if ($elapsed === null) {
            return response(view('honeypot.released'))->header('Cache-Control', 'no-store');
        }

        return response(view('honeypot.trap', [
            'preview' => false,
            'elapsed' => $elapsed,
            'phase' => HoneypotTrap::phase($elapsed),
            'horror' => HoneypotTrap::horrorSeconds(),
            'kewer' => HoneypotTrap::kewerSeconds(),
            'remaining' => HoneypotTrap::total() - $elapsed,
            'samples' => HoneypotTrap::samples(),
            'seek' => HoneypotTrap::seek($request),
        ]))->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * The whole trap from second zero for a signed-in admin, to check it on the live site. No hold is started, nothing is
     * logged and the browser stores no hold, so nobody is locked out and the shop stays open in other tabs. Anyone who is
     * not a signed-in admin gets a 404, so the address gives nothing away. It ends at Home like the real thing.
     */
    public function preview(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 404, headers: ['Cache-Control' => 'no-store']);

        return response(view('honeypot.trap', [
            'preview' => true,
            'elapsed' => 0,
            'phase' => HoneypotTrap::phase(0),
            'horror' => HoneypotTrap::horrorSeconds(),
            'kewer' => HoneypotTrap::kewerSeconds(),
            'remaining' => HoneypotTrap::total(),
            'samples' => HoneypotTrap::samples(),
            'seek' => null,
        ]))->header('Cache-Control', 'no-store, max-age=0');
    }
}

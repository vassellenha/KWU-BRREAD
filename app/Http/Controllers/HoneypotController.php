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
        if (! HoneypotTrap::enabled()) {
            abort(404);
        }

        // Signed in: never trapped, and there is no admin panel here either. The page only clears any old hold and goes Home.
        if ($request->user()) {
            return response(view('honeypot.released'))->header('Cache-Control', 'no-store');
        }

        $elapsed = HoneypotTrap::elapsed($request);

        // No hold yet: this visit starts one, unless it is only the browser resuming a hold that is over.
        if ($elapsed === null && ! $request->boolean('resume') && HoneypotTrap::begin($request)) {
            $elapsed = HoneypotTrap::elapsed($request);
        }

        // Over, or left alone: the page clears the browser's copy of the hold and goes to Home.
        if ($elapsed === null) {
            return response(view('honeypot.released'))->header('Cache-Control', 'no-store');
        }

        return response(view('honeypot.trap', [
            'elapsed' => $elapsed,
            'phase' => HoneypotTrap::phase($elapsed),
            'horror' => HoneypotTrap::horrorSeconds(),
            'kewer' => HoneypotTrap::kewerSeconds(),
            'remaining' => HoneypotTrap::total() - $elapsed,
        ]))->header('Cache-Control', 'no-store, max-age=0');
    }
}

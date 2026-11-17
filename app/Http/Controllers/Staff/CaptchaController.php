<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\LoginCaptcha;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaptchaController extends Controller
{
    /**
     * Every request draws a fresh code, so the refresh button on the form is just a new request.
     */
    public function __invoke(Request $request): Response
    {
        abort_unless(LoginCaptcha::enabled(), 404);

        return response(LoginCaptcha::image($request), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}

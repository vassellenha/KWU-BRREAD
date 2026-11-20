<?php

namespace Tests\Feature;

use App\Support\LoginCaptcha;
use ReflectionMethod;
use Tests\TestCase;

class LoginCaptchaTest extends TestCase
{
    private function renderWith(string $font): string
    {
        $render = new ReflectionMethod(LoginCaptcha::class, 'render');

        return $render->invoke(null, 'AB7K9', $font);
    }

    public function test_the_captcha_is_drawn_with_the_bundled_font(): void
    {
        $png = $this->renderWith(resource_path('fonts/DejaVuSans-Bold.ttf'));

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertSame([300, 84], array_slice(getimagesizefromstring($png), 0, 2));
    }

    public function test_the_captcha_is_still_drawn_when_the_font_file_is_missing(): void
    {
        $png = $this->renderWith(resource_path('fonts/does-not-exist.ttf'));

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertSame([300, 84], array_slice(getimagesizefromstring($png), 0, 2));
    }

    public function test_the_captcha_route_serves_a_png(): void
    {
        config(['auth.login_captcha' => true]);

        $this->get('/captcha')->assertOk()->assertHeader('Content-Type', 'image/png');
    }
}

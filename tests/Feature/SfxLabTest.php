<?php

namespace Tests\Feature;

use Tests\TestCase;

class SfxLabTest extends TestCase
{
    public function test_the_audition_page_opens_in_the_local_environment(): void
    {
        $this->withoutVite();
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/sfx-lab')->assertOk()->assertSee('SFX lab');
    }

    public function test_the_audition_page_is_not_found_in_production_even_with_debug_on(): void
    {
        $this->withoutVite();
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $this->get('/sfx-lab')->assertNotFound();
    }

    public function test_the_audition_page_is_not_found_when_debug_is_off(): void
    {
        $this->withoutVite();
        $this->app->detectEnvironment(fn () => 'staging');
        config(['app.debug' => false]);

        $this->get('/sfx-lab')->assertNotFound();
    }
}

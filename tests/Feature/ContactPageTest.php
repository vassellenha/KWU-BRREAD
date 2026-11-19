<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_shows_the_new_location_and_no_old_address_or_email(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSeeText('Minangkabau, Tel-U')
            ->assertSeeText('BISA ONLINE')
            ->assertSeeText('Jl. Minangkabau Barat No. 50')
            ->assertDontSeeText('Melawai')
            ->assertDontSee('hello@brread.id')
            ->assertDontSee('mailto:', false);
    }

    public function test_instagram_replaces_the_email(): void
    {
        $this->get('/contact')
            ->assertSee('href="https://www.instagram.com/brread.official/"', false)
            ->assertSeeText('@brread.official')
            ->assertSeeText('Message us on Instagram');
    }

    public function test_without_a_key_the_keyless_google_embed_is_used(): void
    {
        config(['services.google_maps.key' => null]);

        $this->get('/contact')
            ->assertSee('src="https://maps.google.com/maps?', false)
            ->assertSee('output=embed', false)
            ->assertDontSee('maps/embed/v1/place', false);
    }

    public function test_with_a_key_the_maps_embed_api_is_used(): void
    {
        config(['services.google_maps.key' => 'TEST_KEY_123']);

        $this->get('/contact')
            ->assertSee('https://www.google.com/maps/embed/v1/place?key=TEST_KEY_123', false)
            ->assertSee('Minangkabau', false);
    }

    public function test_the_open_in_google_maps_link_points_at_the_chosen_place(): void
    {
        $this->get('/contact')
            ->assertSee('ftid=0x2e69f5d99fe71b5b:0x4613509b4a40b539', false)
            ->assertSee('maps/dir/?api=1&amp;destination=', false);
    }

    public function test_the_security_policy_lets_the_page_frame_google_maps_and_nothing_else(): void
    {
        $csp = $this->get('/contact')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('frame-src https://www.google.com https://maps.google.com', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_the_footer_and_home_page_use_the_new_details(): void
    {
        $this->get('/')->assertDontSeeText('Melawai')->assertSee('instagram.com/brread.official', false);
        $this->get('/about')->assertDontSeeText('Melawai')->assertSeeText('Minangkabau, Tel-U');
    }
}

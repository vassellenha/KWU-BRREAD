<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_privacy_page_renders_and_is_linked_from_footer(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('What we collect');
        $this->get('/menu')->assertOk()->assertSee(route('privacy'), false);
    }

    public function test_pages_have_share_metadata_and_no_em_dash_title(): void
    {
        $html = $this->get('/menu')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Menu | Brread! Singapore Ice Cream Sandwich</title>', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('img/social-preview.jpg', $html);
    }
}

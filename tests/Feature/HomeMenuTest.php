<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\SignatureCombo;
use App\Support\HomeMenu;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\SignatureComboSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        $this->seed(SignatureComboSeeder::class);
    }

    public function test_home_copy_and_counts_follow_the_menu(): void
    {
        $menu = HomeMenu::get(public: true);

        $this->assertSame(Component::where('category', 'ice_cream')->where('is_active', true)->whereNotIn('name', ['Vanilla', 'Strawberry'])->count(), $menu['counts']['flavors']);

        $this->get('/')->assertOk()
            ->assertSee('Matcha')
            ->assertSee('Marshmallow Mini')
            ->assertSee('Choose from 2 breads, '.$menu['counts']['flavors'].' ice cream flavors and '.$menu['counts']['toppings'].' toppings', false);
    }

    public function test_a_hidden_flavor_and_a_new_flavor_show_up_on_home_and_about(): void
    {
        Component::where('name', 'Matcha')->update(['is_active' => false]);
        Component::create(['name' => 'Taro', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#B9A4D6', 'stock_quantity' => 50, 'unit' => 'pcs', 'reorder_point' => 10, 'is_active' => true]);

        foreach (['/', '/about'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('Taro', $html);
            $this->assertStringNotContainsString('Matcha', $html);
        }

        $this->assertContains('Taro', array_column(HomeMenu::get()['hero'], 'label'));
        $this->assertNotContains('Matcha', array_column(HomeMenu::get()['hero'], 'label'));
    }

    public function test_signature_cards_come_from_the_combos_the_admin_manages(): void
    {
        $this->assertCount(SignatureCombo::where('is_active', true)->count(), HomeMenu::get()['combos']);

        SignatureCombo::where('name', 'Classic Vanilla Slab')->update(['is_active' => false]);
        SignatureCombo::where('name', 'Es Teler Sprinkle Stack')->update(['name' => 'Es Teler Party']);

        $names = array_column(HomeMenu::get()['combos'], 'name');
        $this->assertNotContains('Classic Vanilla Slab', $names);
        $this->assertContains('Es Teler Party', $names);
        $this->get('/')->assertSee('Es Teler Party')->assertDontSee('Classic Vanilla Slab');
    }

    public function test_home_leaves_vanilla_and_strawberry_out_but_about_keeps_them(): void
    {
        $home = HomeMenu::get(public: true);

        $this->assertNotContains('Vanilla', $home['flavors']);
        $this->assertNotContains('Strawberry', $home['flavors']);
        $this->assertNotContains('vanilla', array_column($home['hero'], 'key'));
        $this->assertNotContains('strawberry', array_column($home['hero'], 'key'));
        foreach ($home['combos'] as $combo) {
            $this->assertNotContains(strtolower($combo['state']['flavor'] ?? ''), ['vanilla', 'strawberry']);
        }
        $this->assertSame(count($home['flavors']), $home['counts']['flavors']);

        $this->assertContains('Vanilla', HomeMenu::get()['flavors']);
    }

    public function test_hero_chip_dresses_the_sandwich_like_a_combo_with_that_flavor(): void
    {
        $strawberry = collect(HomeMenu::get()['hero'])->firstWhere('key', 'strawberry');

        $this->assertTrue($strawberry['state']['sprinkles']);
        $this->assertFalse($strawberry['state']['sauce']);
        $this->assertStringContainsString('strawberry', $strawberry['summary']);
    }

    public function test_the_join_helper_reads_like_a_sentence(): void
    {
        $this->assertSame('A', HomeMenu::join(['A']));
        $this->assertSame('A or B', HomeMenu::join(['A', 'B']));
        $this->assertSame('A, B and C', HomeMenu::join(['A', 'B', 'C'], 'and'));
        $this->assertSame('', HomeMenu::join([]));
    }

    public function test_about_shows_a_card_for_each_flavor_we_sell_and_none_for_the_hidden_ones(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        foreach (['Chocolate', 'Neapolitan', 'Es Teler', 'Durian Musang King', 'Matcha'] as $flavor) {
            $this->assertStringContainsString('<h2 class="about-card-title">'.$flavor.'</h2>', $html);
        }
        $this->assertStringNotContainsString('<h2 class="about-card-title">Vanilla</h2>', $html);
        $this->assertStringNotContainsString('<h2 class="about-card-title">Strawberry</h2>', $html);
        $this->assertStringNotContainsString('product-vanilla', $html);
    }
}

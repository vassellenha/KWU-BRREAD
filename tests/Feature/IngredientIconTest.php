<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Support\IngredientIcon;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The hand-drawn ingredient icons: one sprite on every page, the same list on the server and in the
 * browser, every symbol drawn as SVG (no raster, no icon library), and shown on the order status receipt.
 */
class IngredientIconTest extends TestCase
{
    use RefreshDatabase;

    private function sprite(): string
    {
        return file_get_contents(resource_path('views/partials/ingredient-icons.blade.php'));
    }

    public function test_every_listed_icon_is_drawn_in_the_sprite_as_vector_art(): void
    {
        $sprite = $this->sprite();

        foreach (IngredientIcon::SYMBOLS as $name => $id) {
            $this->assertMatchesRegularExpression('/<symbol id="'.preg_quote($id, '/').'" viewBox="0 0 24 24">/', $sprite, "{$name} has no symbol");
        }

        $this->assertStringNotContainsString('<image', $sprite);
        $this->assertStringNotContainsString('data:image', $sprite);
        $this->assertSame(count(IngredientIcon::SYMBOLS), substr_count($sprite, '<symbol '));
    }

    public function test_the_browser_list_matches_the_server_list(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        preg_match('/export const INGREDIENT_ICONS = \{(.*?)\};/s', $script, $block);
        $this->assertNotEmpty($block);

        preg_match_all("/(?:'([^']+)'|(\\w+)): '(ing-[a-z-]+)'/", $block[1], $pairs, PREG_SET_ORDER);
        $browser = collect($pairs)->mapWithKeys(fn (array $pair): array => [($pair[1] !== '' ? $pair[1] : $pair[2]) => $pair[3]])->all();

        $this->assertEqualsCanonicalizing(IngredientIcon::SYMBOLS, $browser);
    }

    public function test_the_four_toppings_and_the_drawn_flavors_have_icons(): void
    {
        foreach (['Chocolate Sauce', 'Rainbow Sprinkles', 'Marshmallow Mini', 'Yupi Gummy Bear Mini', 'Matcha', 'Neapolitan', 'Durian Musang King', 'Es Teler'] as $name) {
            $this->assertNotNull(IngredientIcon::for($name), $name);
        }

        // The photo flavors keep their plain colour dot.
        $this->assertNull(IngredientIcon::for('Chocolate'));
        $this->assertNull(IngredientIcon::for('Vanilla'));
        $this->assertNull(IngredientIcon::for(null));
    }

    public function test_pages_carry_the_sprite_once(): void
    {
        $this->withoutVite();

        foreach (['/builder', '/menu', '/'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'id="ing-yupi-gummy-bear-mini"'), $path);
        }
    }

    public function test_the_order_status_receipt_shows_the_topping_icons(): void
    {
        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        $ids = Component::whereIn('name', ['Plain White Bread', 'Matcha', 'Marshmallow Mini', 'Yupi Gummy Bear Mini'])->pluck('id', 'name');

        $number = $this->postJson('/api/orders', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'items' => [['component_ids' => [$ids['Plain White Bread'], $ids['Matcha'], $ids['Marshmallow Mini'], $ids['Yupi Gummy Bear Mini']], 'quantity' => 1]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->forceFill(['status' => Order::STATUS_CONFIRMED, 'confirmation_token' => Str::random(40), 'confirmed_at' => now()])->save();

        $this->get("/order/status/{$order->confirmation_token}")
            ->assertOk()
            ->assertSee('<use href="#ing-marshmallow-mini">', false)
            ->assertSee('<use href="#ing-yupi-gummy-bear-mini">', false)
            ->assertSee('<use href="#ing-matcha">', false);
    }
}

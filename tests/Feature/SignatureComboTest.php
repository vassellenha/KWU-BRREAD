<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\SignatureCombo;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\HomeMenu;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\SignatureComboSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SignatureComboTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
        $this->seed(SignatureComboSeeder::class);
        $this->admin = User::factory()->withRole(User::ROLE_ADMIN)->create();
    }

    private function item(string $name): Component
    {
        return Component::where('name', $name)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $name = 'Sunday Special'): array
    {
        return [
            'name' => $name,
            'bread_id' => $this->item('Whole Wheat Bread')->id,
            'flavor_id' => $this->item('Es Teler')->id,
            'toppings' => [$this->item('Chocolate Sauce')->id, $this->item('Rainbow Sprinkles')->id],
        ];
    }

    public function test_the_starter_combos_are_seeded_once_and_a_deleted_one_stays_gone(): void
    {
        $this->assertSame(7, SignatureCombo::count());

        SignatureCombo::where('name', 'Classic Vanilla Slab')->firstOrFail()->delete();
        $this->seed(SignatureComboSeeder::class);

        $this->assertSame(6, SignatureCombo::count());
        $this->assertSame('1', SiteSetting::allValues()['signature_combos_seeded']);
    }

    public function test_the_api_lists_active_combos_in_order_by_ingredient_name(): void
    {
        SignatureCombo::where('name', 'Wheat Vanilla Drizzle')->firstOrFail()->update(['is_active' => false]);

        $response = $this->getJson('/api/components')->assertOk();

        $names = collect($response->json('signatures'))->pluck('name');
        $this->assertCount(6, $names);
        $this->assertSame('Classic Vanilla Slab', $names->first());
        $this->assertNotContains('Wheat Vanilla Drizzle', $names->all());
        $this->assertContains([
            'name' => 'Es Teler Sprinkle Stack',
            'bread' => 'Plain White Bread',
            'flavor' => 'Es Teler',
            'toppings' => ['Rainbow Sprinkles'],
        ], $response->json('signatures'));
    }

    public function test_a_combo_with_a_hidden_flavor_is_still_served_so_the_builder_can_grey_it_out(): void
    {
        $this->item('Strawberry')->update(['is_active' => false]);

        $flavors = collect($this->getJson('/api/components')->json('signatures'))->pluck('flavor');

        $this->assertContains('Strawberry', $flavors->all());
    }

    public function test_the_admin_sees_the_combos_on_the_menu_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('staff.menu.index'))
            ->assertOk()
            ->assertSee('Signature combos')
            ->assertSee('Neapolitan Trio Slab')
            ->assertSee('Rp 14.000');
    }

    public function test_the_admin_can_add_a_combo_with_toppings(): void
    {
        $this->actingAs($this->admin)
            ->post(route('staff.menu.combos.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $combo = SignatureCombo::where('name', 'Sunday Special')->firstOrFail();

        $this->assertSame(8, $combo->sort_order);
        $this->assertTrue($combo->is_active);
        $this->assertSame(['Chocolate Sauce', 'Rainbow Sprinkles'], $combo->toppings->pluck('name')->all());
        $this->assertSame(7000 + 8000 + 3000 + 3000, $combo->price());
    }

    public function test_the_admin_can_edit_a_combo(): void
    {
        $combo = SignatureCombo::where('name', 'Classic Vanilla Slab')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('staff.menu.combos.update', $combo), $this->payload('Classic Vanilla Slab'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $combo->refresh();
        $this->assertSame('Es Teler', $combo->flavor->name);
        $this->assertCount(2, $combo->toppings);

        $this->actingAs($this->admin)
            ->put(route('staff.menu.combos.update', $combo), ['name' => 'Classic Vanilla Slab', 'bread_id' => $combo->bread_id, 'flavor_id' => $combo->flavor_id])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $combo->fresh()->toppings);
    }

    public function test_a_combo_needs_a_unique_name_and_real_ingredients_of_the_right_kind(): void
    {
        $this->actingAs($this->admin)
            ->post(route('staff.menu.combos.store'), $this->payload('classic vanilla slab'))
            ->assertSessionHasErrors('name');

        $payload = $this->payload();
        $payload['bread_id'] = $this->item('Vanilla')->id;
        $payload['flavor_id'] = $this->item('Plain White Bread')->id;
        $payload['toppings'] = [$this->item('Neapolitan')->id];

        $this->actingAs($this->admin)
            ->post(route('staff.menu.combos.store'), $payload)
            ->assertSessionHasErrors(['bread_id', 'flavor_id', 'toppings.0']);

        $this->assertSame(7, SignatureCombo::count());
    }

    public function test_the_admin_can_hide_move_and_delete_a_combo(): void
    {
        $second = SignatureCombo::orderBy('sort_order')->skip(1)->firstOrFail();

        $this->actingAs($this->admin)->post(route('staff.menu.combos.move', $second), ['direction' => 'up'])->assertRedirect();
        $this->assertSame($second->name, SignatureCombo::orderBy('sort_order')->first()->name);
        $this->assertSame(range(1, 7), SignatureCombo::orderBy('sort_order')->pluck('sort_order')->all());

        $this->actingAs($this->admin)->post(route('staff.menu.combos.move', $second), ['direction' => 'up'])->assertRedirect();
        $this->assertSame($second->name, SignatureCombo::orderBy('sort_order')->first()->name);

        $this->actingAs($this->admin)->post(route('staff.menu.combos.toggle', $second))->assertRedirect();
        $this->assertFalse($second->fresh()->is_active);

        $this->actingAs($this->admin)->delete(route('staff.menu.combos.destroy', $second))->assertRedirect();
        $this->assertModelMissing($second);
        $this->assertSame(6, SignatureCombo::count());
    }

    public function test_a_menu_item_that_is_part_of_a_combo_cannot_be_deleted(): void
    {
        $sprinkles = $this->item('Rainbow Sprinkles');

        $this->actingAs($this->admin)
            ->delete(route('staff.menu.destroy', $sprinkles))
            ->assertSessionHasErrors('delete');

        $this->assertModelExists($sprinkles);
    }

    public function test_only_the_admin_can_change_combos(): void
    {
        $combo = SignatureCombo::firstOrFail();
        $warehouse = User::factory()->withRole(User::ROLE_WAREHOUSE)->create();

        $this->actingAs($warehouse)->post(route('staff.menu.combos.store'), $this->payload())->assertForbidden();
        $this->actingAs($warehouse)->put(route('staff.menu.combos.update', $combo), $this->payload())->assertForbidden();
        $this->actingAs($warehouse)->post(route('staff.menu.combos.toggle', $combo))->assertForbidden();
        $this->actingAs($warehouse)->delete(route('staff.menu.combos.destroy', $combo))->assertForbidden();

        $this->assertSame(7, SignatureCombo::count());
    }

    public function test_the_admin_can_put_a_photo_on_a_combo_card_and_take_it_off(): void
    {
        $combo = SignatureCombo::firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('staff.menu.combos.update', $combo), $this->payload($combo->name) + ['photo' => UploadedFile::fake()->image('card.jpg', 800, 800)])
            ->assertSessionHasNoErrors();

        $photo = $combo->fresh()->photo;
        $this->assertNotNull($photo);

        $card = collect(HomeMenu::get(public: true)['combos'])->firstWhere('id', $combo->id);
        $this->assertTrue($card['photo']);
        $this->assertSame($photo->url(), $card['image']);

        $this->get(route('signature.photo', $photo))->assertOk()->assertHeader('Content-Type', $photo->mime);

        $this->actingAs($this->admin)
            ->put(route('staff.menu.combos.update', $combo), $this->payload($combo->name) + ['remove_photo' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertNull($combo->fresh()->photo);
        $this->assertFalse(collect(HomeMenu::get(public: true)['combos'])->firstWhere('id', $combo->id)['photo']);
    }

    public function test_a_combo_photo_must_be_a_real_picture(): void
    {
        $combo = SignatureCombo::firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('staff.menu.combos.update', $combo), $this->payload($combo->name) + ['photo' => UploadedFile::fake()->create('card.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('photo');

        $this->assertNull($combo->fresh()->photo);
    }
}

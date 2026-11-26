<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\ReviewPhoto;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function reviewed(string $name, int $rating, string $text, bool $photo = false): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $number = $this->postJson('/api/orders', [
            'customer_name' => $name,
            'customer_phone' => '081200000011',
            'items' => [['component_ids' => [$bread->id, $flavor->id], 'quantity' => 1]],
        ])->assertCreated()->json('order_number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $order->update(['rating' => $rating, 'review_text' => $text, 'reviewed_at' => now()]);
        if ($photo) {
            $order->reviewPhoto()->create(['mime' => 'image/webp', 'width' => 300, 'height' => 300, 'data' => base64_encode('x')]);
        }

        return $order;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_only_the_superadmin_opens_the_reviews_module(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE]))->get('/staff/reviews')->assertForbidden();
        $this->actingAs($this->admin())->get('/staff/reviews')->assertOk()->assertSee('Reviews');
    }

    public function test_it_lists_filters_and_searches_reviews(): void
    {
        $this->reviewed('Budi Santoso', 5, 'Enak banget', photo: true);
        $this->reviewed('Jahil Iseng', 1, 'sampah');
        $hidden = $this->reviewed('Sari Dewi', 4, 'Lumayan');
        $hidden->update(['review_hidden_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/staff/reviews')->assertSee('Budi Santoso')->assertSee('Jahil Iseng')->assertSee('Sari Dewi');
        $this->actingAs($admin)->get('/staff/reviews?show=low')->assertSee('Jahil Iseng')->assertDontSee('Budi Santoso');
        $this->actingAs($admin)->get('/staff/reviews?show=photo')->assertSee('Budi Santoso')->assertDontSee('Jahil Iseng');
        $this->actingAs($admin)->get('/staff/reviews?show=hidden')->assertSee('Sari Dewi')->assertDontSee('Budi Santoso');
        $this->actingAs($admin)->get('/staff/reviews?q=sampah')->assertSee('Jahil Iseng')->assertDontSee('Sari Dewi');
    }

    public function test_a_hidden_review_leaves_the_public_pages(): void
    {
        $order = $this->reviewed('Jahil Iseng', 1, 'sampah');
        $admin = $this->admin();

        $this->get('/reviews')->assertSee('sampah');
        $this->actingAs($admin)->post(route('staff.site.reviews.toggle', $order))->assertRedirect();
        $this->get('/reviews')->assertDontSee('sampah');
    }

    public function test_deleting_a_review_removes_stars_words_and_photo_but_keeps_the_order(): void
    {
        $order = $this->reviewed('Jahil Iseng', 1, 'sampah', photo: true);

        $this->actingAs($this->admin())->delete(route('staff.reviews.destroy', $order))->assertSessionHas('status');

        $order->refresh();
        $this->assertNull($order->rating);
        $this->assertNull($order->review_text);
        $this->assertSame(0, ReviewPhoto::where('order_id', $order->id)->count());
        $this->assertNotNull(Order::find($order->id));
    }

    public function test_deleting_needs_a_review_and_the_right_role(): void
    {
        $plain = $this->reviewed('Budi', 5, 'ok');
        $plain->update(['rating' => null, 'review_text' => null]);

        $this->actingAs($this->admin())->delete(route('staff.reviews.destroy', $plain))->assertNotFound();

        $order = $this->reviewed('Sari', 4, 'ok');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE]))->delete(route('staff.reviews.destroy', $order))->assertForbidden();
        $this->assertSame(4, $order->fresh()->rating);
    }

    public function test_the_migration_gives_irgis_review_his_photo(): void
    {
        $order = $this->reviewed('Irgi Pratama', 5, 'Gw nyesel belinya, gw nyesel cmn beli 1');

        $migration = require base_path('database/migrations/2026_11_26_200000_add_irgi_review_photo.php');
        $migration->up();

        $photo = ReviewPhoto::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('image/webp', $photo->mime);
        $this->assertSame(1, DB::table('review_photos')->where('order_id', $order->id)->count());

        $migration->up();
        $this->assertSame(1, DB::table('review_photos')->where('order_id', $order->id)->count());
    }
}

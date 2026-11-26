<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\ReviewPhoto;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use App\Support\ReviewFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_the_admin_adds_a_review_for_a_finished_order(): void
    {
        $order = $this->reviewed('Budi', 5, 'ok');
        $order->update(['rating' => null, 'review_text' => null, 'reviewed_at' => null, 'status' => Order::STATUS_COMPLETED]);

        $this->actingAs($this->admin())->post(route('staff.reviews.store'), [
            'order_id' => $order->id,
            'rating' => 4,
            'review_text' => 'Enak, esnya pas',
            'photo' => UploadedFile::fake()->image('x.jpg', 600, 800),
        ])->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(4, $order->rating);
        $this->assertSame('Enak, esnya pas', $order->review_text);
        $this->assertNotNull($order->reviewPhoto);
    }

    public function test_the_admin_cannot_review_an_unfinished_or_already_reviewed_order(): void
    {
        $admin = $this->admin();
        $pending = $this->reviewed('Budi', 5, 'ok');
        $pending->update(['rating' => null, 'review_text' => null, 'status' => Order::STATUS_PENDING]);

        $this->actingAs($admin)->post(route('staff.reviews.store'), ['order_id' => $pending->id, 'rating' => 5, 'review_text' => 'halo'])
            ->assertSessionHasErrors('order_id');
        $this->assertNull($pending->fresh()->rating);

        $done = $this->reviewed('Sari', 3, 'sudah ada');
        $done->update(['status' => Order::STATUS_COMPLETED]);
        $this->actingAs($admin)->post(route('staff.reviews.store'), ['order_id' => $done->id, 'rating' => 1, 'review_text' => 'ganti'])
            ->assertSessionHasErrors('order_id');
        $this->assertSame(3, $done->fresh()->rating);
    }

    public function test_the_admin_sets_the_frame_and_replaces_the_photo(): void
    {
        $order = $this->reviewed('Budi', 5, 'Enak banget', photo: true);

        $this->actingAs($this->admin())->post(route('staff.reviews.photo', $order), [
            'fit' => 'contain',
            'zoom' => 150,
            'focus_x' => 30,
            'focus_y' => 70,
            'photo' => UploadedFile::fake()->image('baru.jpg', 700, 500),
        ])->assertSessionHas('status');

        $photo = $order->fresh()->reviewPhoto;
        $this->assertSame('contain', $photo->fit);
        $this->assertSame(150, $photo->zoom);
        $this->assertSame(30, $photo->focus_x);
        $this->assertSame(70, $photo->focus_y);
        $this->assertSame(700, $photo->width);

        $this->actingAs($this->admin())->post(route('staff.reviews.photo', $order), ['fit' => 'bogus', 'zoom' => 150, 'focus_x' => 30, 'focus_y' => 70])
            ->assertSessionHasErrors('fit');
    }

    public function test_a_photo_can_be_added_to_a_review_that_has_none_but_not_without_a_file(): void
    {
        $order = $this->reviewed('Budi', 5, 'Enak banget');

        $this->actingAs($this->admin())->post(route('staff.reviews.photo', $order), ['fit' => 'auto', 'zoom' => 100, 'focus_x' => 50, 'focus_y' => 50])
            ->assertSessionHasErrors('photo');

        $this->actingAs($this->admin())->post(route('staff.reviews.photo', $order), [
            'fit' => 'auto', 'zoom' => 100, 'focus_x' => 50, 'focus_y' => 50,
            'photo' => UploadedFile::fake()->image('x.jpg', 600, 800),
        ])->assertSessionHas('status');
        $this->assertNotNull($order->fresh()->reviewPhoto);
    }

    public function test_auto_fit_fills_the_frame_for_near_portrait_photos_and_shows_wide_ones_whole(): void
    {
        $portrait = new ReviewPhoto(['width' => 960, 'height' => 1280]);
        $wide = new ReviewPhoto(['width' => 1600, 'height' => 900]);
        $forced = new ReviewPhoto(['width' => 1600, 'height' => 900, 'fit' => 'cover', 'zoom' => 150, 'focus_x' => 20, 'focus_y' => 80]);

        $this->assertSame('cover', $portrait->resolvedFit());
        $this->assertSame('contain', $wide->resolvedFit());
        $this->assertSame('--fit:cover;--fx:20%;--fy:80%;--zoom:1.5', $forced->frameStyle());
    }

    public function test_the_public_feed_carries_the_frame_of_each_photo(): void
    {
        $order = $this->reviewed('Budi', 5, 'Enak banget', photo: true);
        $order->reviewPhoto->update(['fit' => 'contain', 'zoom' => 120]);

        $this->assertStringContainsString('--fit:contain', ReviewFeed::photos()->first()['frame']);
        $this->get('/reviews')->assertOk()->assertSee('review-frame', false)->assertSee('--zoom:1.2', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Order;
use App\Models\ReviewPhoto;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReviewPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ComponentSeeder::class);
    }

    private function confirmedOrder(string $name = 'Budi Santoso'): Order
    {
        $bread = Component::where('category', 'bread')->firstOrFail();
        $flavor = Component::where('category', 'ice_cream')->firstOrFail();

        $order = Order::create([
            'order_number' => 'BRREAD-'.strtoupper(substr(md5($name.microtime()), 0, 6)),
            'total_price' => 17000,
            'status' => Order::STATUS_CONFIRMED,
            'confirmation_token' => bin2hex(random_bytes(16)),
            'confirmed_at' => now(),
            'customer_name' => $name,
            'customer_phone' => '081234567890',
        ]);
        foreach ([$bread, $flavor] as $component) {
            $order->orderItems()->create(['component_id' => $component->id, 'creation_index' => 0, 'layer_order' => $component->category === 'bread' ? 0 : 1, 'quantity' => 1, 'unit_price' => $component->price]);
        }

        return $order;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('sandwich.jpg', 1600, 1200);
    }

    public function test_a_customer_can_add_a_photo_with_a_review(): void
    {
        $order = $this->confirmedOrder();

        $this->post("/order/status/{$order->confirmation_token}/review", [
            'rating' => 5,
            'review_text' => 'Rotinya empuk, es krimnya masih beku.',
            'photo' => $this->photo(),
        ])->assertRedirect();

        $photo = $order->fresh()->reviewPhoto;
        $this->assertNotNull($photo);
        // downscaled and re-encoded on the server, never kept as uploaded
        $this->assertLessThanOrEqual(1280, max($photo->width, $photo->height));
        $this->assertContains($photo->mime, ['image/webp', 'image/jpeg']);

        $this->get($photo->url())
            ->assertOk()
            ->assertHeader('Content-Type', $photo->mime)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_photo_without_a_review_is_refused(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/order/status/{$order->confirmation_token}/review", [
            'rating' => 5,
            'photo' => $this->photo(),
        ])->assertUnprocessable()->assertJsonValidationErrors('review_text');

        $this->postJson("/order/status/{$order->confirmation_token}/review", [
            'rating' => 5,
            'review_text' => 'enak',
            'photo' => $this->photo(),
        ])->assertUnprocessable()->assertJsonValidationErrors('review_text');

        $this->assertNull($order->fresh()->rating);
        $this->assertSame(0, ReviewPhoto::count());
    }

    public function test_a_file_that_is_not_a_photo_is_refused(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/order/status/{$order->confirmation_token}/review", [
            'rating' => 4,
            'review_text' => 'Enak banget, pengiriman cepat.',
            'photo' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_a_review_without_a_photo_still_works(): void
    {
        $order = $this->confirmedOrder();

        $this->postJson("/order/status/{$order->confirmation_token}/review", ['rating' => 4])->assertOk();

        $this->assertSame(4, $order->fresh()->rating);
    }

    public function test_staff_and_the_superadmin_can_only_look(): void
    {
        $order = $this->confirmedOrder();

        foreach ([User::ROLE_ADMIN, User::ROLE_WAREHOUSE] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get("/order/status/{$order->confirmation_token}")
                ->assertOk()
                ->assertSee('Mode lihat saja')
                ->assertDontSee('Kirim Ulasan');

            $this->actingAs($user)->postJson("/order/status/{$order->confirmation_token}/review", [
                'rating' => 1,
                'review_text' => 'Rekayasa rating dari akun staf.',
            ])->assertForbidden();
        }

        $this->assertNull($order->fresh()->rating);
    }

    public function test_photo_reviews_lead_home_and_words_only_reviews_sit_under_them(): void
    {
        $withPhoto = $this->confirmedOrder('Siti Aminah Rahma');
        $this->post("/order/status/{$withPhoto->confirmation_token}/review", ['rating' => 5, 'review_text' => 'Foto dari kantor, dingin banget!', 'photo' => $this->photo()]);
        $wordsOnly = $this->confirmedOrder('Andi Pratama');
        $this->post("/order/status/{$wordsOnly->confirmation_token}/review", ['rating' => 4, 'review_text' => 'Topping sprinkles-nya banyak, suka.']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Siti R.')
            ->assertSee('Foto dari kantor, dingin banget!')
            ->assertSee($withPhoto->fresh()->reviewPhoto->url(), false)
            ->assertSee('Andi P.')
            ->assertSee('Topping sprinkles-nya banyak, suka.')
            ->assertSee(route('reviews'), false)
            // never the full name or the phone number
            ->assertDontSee('Siti Aminah Rahma')
            ->assertDontSee('081234567890');

        $this->get('/reviews')->assertOk()->assertSee('Siti R.')->assertSee('Andi P.');
        $this->get('/about')->assertOk()->assertSee($withPhoto->fresh()->reviewPhoto->url(), false);
    }

    public function test_the_reviews_page_shows_at_most_thirty_photos(): void
    {
        for ($i = 0; $i < 32; $i++) {
            $order = $this->confirmedOrder('Pelanggan '.$i);
            $order->update(['rating' => 5, 'review_text' => 'Ulasan nomor '.$i, 'reviewed_at' => now()->addSeconds($i)]);
            $order->reviewPhoto()->create(['mime' => 'image/jpeg', 'width' => 10, 'height' => 10, 'data' => base64_encode('x')]);
        }

        $page = $this->get('/reviews')->assertOk();
        $this->assertSame(30, substr_count($page->getContent(), '/reviews/photo/'));
        $page->assertSee('Ulasan nomor 31')->assertDontSee('Ulasan nomor 1"', false);
    }

    public function test_the_superadmin_can_change_the_contact_person_and_take_a_photo_down(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put('/staff/site/contact', [
            'contact_name' => 'Kak Rizky',
            'contact_phone' => '0812 3456 7890',
            'contact_whatsapp' => '0812 3456 7890',
            'instagram_handle' => '@brread.jkt',
        ])->assertRedirect();

        auth()->logout();
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Kak Rizky')
            ->assertSee('tel:+6281234567890', false)
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('@brread.jkt');

        $order = $this->confirmedOrder();
        $this->post("/order/status/{$order->confirmation_token}/review", ['rating' => 5, 'review_text' => 'Foto yang tidak pantas.', 'photo' => $this->photo()]);
        $photo = $order->fresh()->reviewPhoto;

        $this->actingAs($admin)->get('/staff/site')->assertOk()->assertSee('Customer reviews')->assertSee($photo->url(), false);
        $this->actingAs($admin)->delete("/staff/site/photos/{$photo->id}")->assertRedirect();

        $this->assertNull($order->fresh()->reviewPhoto);
        $this->assertSame('Foto yang tidak pantas.', $order->fresh()->review_text);
    }

    public function test_the_superadmin_can_hide_a_review_from_the_site(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = $this->confirmedOrder('Tukang Spam');
        $this->post("/order/status/{$order->confirmation_token}/review", ['rating' => 1, 'review_text' => 'beli pulsa murah di sini']);
        $this->get('/')->assertSee('beli pulsa murah di sini');

        $this->actingAs($admin)->post("/staff/site/reviews/{$order->id}/toggle")->assertRedirect();
        auth()->logout();

        $this->get('/')->assertDontSee('beli pulsa murah di sini');
        $this->get('/reviews')->assertDontSee('beli pulsa murah di sini');
        // still on the customer's own page and in the data
        $this->get("/order/status/{$order->confirmation_token}")->assertSee('beli pulsa murah di sini');
        $this->assertSame(1, $order->fresh()->rating);
    }

    public function test_only_the_superadmin_can_open_the_website_settings(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_WAREHOUSE]);

        $this->actingAs($staff)->get('/staff/site')->assertForbidden();
        $this->actingAs($staff)->put('/staff/site/contact', ['contact_phone' => '0812', 'instagram_handle' => 'x'])->assertForbidden();
    }
}

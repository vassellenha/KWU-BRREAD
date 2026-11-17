<?php

namespace Tests\Feature;

use App\Support\Lockdown;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginLockdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(UserSeeder::class);
        Cache::flush();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function payloads(): array
    {
        return [
            'sql quote' => ["' OR '1'='1"],
            'sql comment' => ['admin--'],
            'union' => ['x union select password'],
            'script tag' => ['<script>alert(1)</script>'],
            'path traversal' => ['../../etc/passwd'],
            'template' => ['{{7*7}}'],
            'very long' => [str_repeat('a', 101)],
        ];
    }

    #[DataProvider('payloads')]
    public function test_an_attack_payload_as_username_locks_the_address(string $payload): void
    {
        $this->post('/staff', ['username' => $payload, 'password' => 'x'])
            ->assertStatus(429)
            ->assertSee('SIGN-IN HALTED')
            ->assertHeader('Retry-After', (string) Lockdown::PAYLOAD_SECONDS);

        // The lock sticks: even the real credentials and the plain login page are refused now.
        $this->post('/staff', ['username' => 'staffmrp', 'password' => '12345678'])->assertStatus(429);
        $this->get('/staff')->assertStatus(429)->assertSee('SYSTEM LOCKED');
        $this->assertGuest();
    }

    public function test_the_sign_in_script_gets_json_and_the_remaining_seconds(): void
    {
        $this->postJson('/superadmin', ['username' => "admin'--", 'password' => 'x'])
            ->assertStatus(429)
            ->assertJson(['locked' => true, 'reason' => 'payload'])
            ->assertJsonPath('retry_after', Lockdown::PAYLOAD_SECONDS);
    }

    public function test_the_lock_only_covers_the_address_that_sent_the_payload(): void
    {
        $this->post('/staff', ['username' => "x'--", 'password' => 'x'])->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->get('/staff')
            ->assertOk();
    }

    public function test_ordinary_typos_never_trigger_the_lockdown(): void
    {
        foreach (['staffmrp ', 'Staff MRP', 'staf-mrp', 'superadmin'] as $username) {
            $this->postJson('/staff', ['username' => $username, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->get('/staff')->assertOk();
    }

    public function test_a_wrong_password_answers_with_validation_errors_not_a_lockdown(): void
    {
        $this->postJson('/staff', ['username' => 'staffmrp', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_a_correct_login_tells_the_script_where_to_go(): void
    {
        $this->postJson('/staff', ['username' => 'staffmrp', 'password' => '12345678'])
            ->assertOk()
            ->assertJson(['redirect' => url('/staff/orders')]);

        $this->assertAuthenticated();
    }

    public function test_hitting_the_rate_limit_answers_json_with_a_lockdown(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->postJson('/staff', ['username' => 'staffmrp', 'password' => 'wrong']);
        }

        $this->postJson('/staff', ['username' => 'staffmrp', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJson(['locked' => true, 'reason' => 'throttle']);
    }

    public function test_the_address_and_reason_are_written_to_the_log(): void
    {
        Log::spy();

        $this->post('/staff', ['username' => "x'--", 'password' => 'x']);

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $message === 'security.lockdown'
                && $context['reason'] === 'payload'
                && $context['ip'] === '127.0.0.1'
        )->once();
    }

    public function test_the_locked_page_has_the_sound_button_and_no_music_tag_until_a_file_exists(): void
    {
        $response = $this->post('/staff', ['username' => "x'--", 'password' => 'x'])->assertStatus(429);

        $response->assertDontSee('id="lockdown-sound"', false);

        if (! file_exists(public_path('audio/lockdown.mp3'))) {
            $response->assertDontSee('<audio', false);
        }
    }

    public function test_the_normal_login_page_carries_the_hidden_lockdown_overlay(): void
    {
        $this->get('/staff')->assertOk()->assertSee('id="lockdown"', false)->assertSee('data-autostart="0"', false);
    }

    public function test_a_superadmin_can_open_the_unlisted_preview_without_locking_anything(): void
    {
        Log::spy();
        $admin = \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)->first();

        $this->actingAs($admin)->get('/'.config('auth.lockdown_preview_path'))
            ->assertOk()
            ->assertSee('data-preview="1"', false);

        $this->assertSame(0, Lockdown::remaining(request()));
        Log::shouldNotHaveReceived('warning');
    }

    public function test_the_preview_is_a_404_for_guests_and_staff(): void
    {
        $path = '/'.config('auth.lockdown_preview_path');

        $this->get($path)->assertNotFound();

        $staff = \App\Models\User::where('username', 'staffmrp')->first();
        $this->actingAs($staff)->get($path)->assertNotFound();
    }

    public function test_the_locked_page_greets_the_caller_by_the_name_they_typed(): void
    {
        $this->post('/superadmin', ['username' => "admin' OR 1=1 --", 'password' => 'x'])
            ->assertStatus(429)
            ->assertSee('welcome,')
            ->assertSee("admin&#039; OR 1=1 --", false)
            ->assertDontSee('behind the counter')
            ->assertDontSee('<script>', false);

        // A reload still knows who it is talking to.
        $this->get('/superadmin')->assertStatus(429)->assertSee("admin&#039; OR 1=1 --", false);
    }

    public function test_the_greeting_falls_back_to_the_system_and_never_runs_long(): void
    {
        $this->assertSame('Windows user', Lockdown::greeting(null, ['os' => 'Windows 10/11']));
        $this->assertSame('macOS user', Lockdown::greeting('', ['os' => 'macOS 14.5']));
        $this->assertSame('stranger', Lockdown::greeting(null, ['os' => 'Unknown OS']));
        $this->assertSame(str_repeat('a', 22).'...', Lockdown::greeting(str_repeat('a', 300), null));
    }
}

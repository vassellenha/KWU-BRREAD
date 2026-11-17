<?php

namespace Tests\Feature;

use App\Models\IpBlock;
use App\Models\SecurityEvent;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IpBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(UserSeeder::class);
        Cache::flush();
    }

    private function admin(): User
    {
        return User::where('username', 'superadmin')->firstOrFail();
    }

    private function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_the_superadmin_can_block_an_address_and_it_is_shut_out_of_every_page(): void
    {
        $this->actingAs($this->admin())
            ->fromIp('10.0.0.1')
            ->post(route('staff.security.block'), ['ip' => '36.66.1.2', 'note' => 'sqlmap'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('ip_blocks', ['ip' => '36.66.1.2', 'note' => 'sqlmap']);

        auth()->logout();

        foreach (['/', '/menu', '/staff', '/superadmin'] as $path) {
            $this->fromIp('36.66.1.2')->get($path)->assertStatus(403)->assertSee('YOU ARE NOT WELCOME HERE.');
        }

        $this->fromIp('36.66.1.3')->get('/')->assertOk();
        $this->fromIp('36.66.1.2')->get('/up')->assertOk();
        $this->assertSame(1, IpBlock::first()->hits);
    }

    public function test_unblocking_lets_the_address_back_in(): void
    {
        $block = IpBlock::create(['ip' => '36.66.1.2']);
        $this->fromIp('36.66.1.2')->get('/')->assertStatus(403);

        $this->actingAs($this->admin())
            ->fromIp('10.0.0.1')
            ->delete(route('staff.security.unblock', $block))
            ->assertRedirect();

        $this->fromIp('36.66.1.2')->get('/')->assertOk();
    }

    public function test_the_superadmin_cannot_block_their_own_address(): void
    {
        $this->actingAs($this->admin())
            ->fromIp('10.0.0.1')
            ->post(route('staff.security.block'), ['ip' => '10.0.0.1'])
            ->assertSessionHasErrors('ip');

        $this->assertDatabaseCount('ip_blocks', 0);
    }

    public function test_records_for_an_address_can_be_deleted(): void
    {
        SecurityEvent::create(['reason' => 'payload', 'ip' => '36.66.1.2', 'last_seen_at' => now()]);
        SecurityEvent::create(['reason' => 'throttle', 'ip' => '36.66.1.2', 'last_seen_at' => now()]);
        SecurityEvent::create(['reason' => 'payload', 'ip' => '8.8.8.8', 'last_seen_at' => now()]);

        $this->actingAs($this->admin())
            ->fromIp('10.0.0.1')
            ->delete(route('staff.security.forget'), ['ip' => '36.66.1.2'])
            ->assertSessionHas('status', 'Deleted 2 records for 36.66.1.2.');

        $this->assertSame(['8.8.8.8'], SecurityEvent::pluck('ip')->all());
    }

    public function test_staff_cannot_block_or_delete(): void
    {
        $staff = User::where('role', '!=', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($staff)->post(route('staff.security.block'), ['ip' => '36.66.1.2'])->assertForbidden();
        $this->actingAs($staff)->delete(route('staff.security.forget'), ['ip' => '36.66.1.2'])->assertForbidden();
        $this->assertDatabaseCount('ip_blocks', 0);
    }

    public function test_the_security_page_shows_blocks_and_actions(): void
    {
        IpBlock::create(['ip' => '36.66.1.2', 'note' => 'sqlmap']);
        SecurityEvent::create(['reason' => 'payload', 'ip' => '36.66.1.2', 'last_seen_at' => now()]);

        $this->actingAs($this->admin())
            ->get(route('staff.security.index'))
            ->assertOk()
            ->assertSee('Blocked addresses')
            ->assertSee('sqlmap')
            ->assertSee('Unblock')
            ->assertSee('Delete records for 36.66.1.2');
    }

    public function test_the_unblock_command_lifts_a_block(): void
    {
        IpBlock::create(['ip' => '36.66.1.2']);

        $this->artisan('security:unblock', ['ip' => '36.66.1.2'])->expectsOutput('36.66.1.2 is unblocked.')->assertSuccessful();
        $this->assertDatabaseCount('ip_blocks', 0);
    }
}

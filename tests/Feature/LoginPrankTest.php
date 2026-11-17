<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPrankTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.login_prank' => true]);
        User::factory()->create(['username' => 'kasir', 'password' => 'right-password', 'role' => User::ROLE_WAREHOUSE]);
    }

    private function wrong(string $path = '/staff')
    {
        return $this->postJson($path, ['username' => 'kasir', 'password' => 'wrong']);
    }

    public function test_each_wrong_password_brings_the_next_scene_and_the_fourth_pauses_sign_in(): void
    {
        $this->wrong()->assertStatus(422)->assertJsonPath('prank.stage', 1)->assertJsonValidationErrors('username');
        $this->wrong()->assertStatus(422)->assertJsonPath('prank.stage', 2);
        $this->wrong()->assertStatus(422)->assertJsonPath('prank.stage', 3)->assertJsonPath('prank.ip', '127.0.0.1');
        $this->wrong()->assertStatus(422)->assertJsonPath('prank.stage', 4)->assertJsonPath('prank.retry_after', 60);

        // Paused: even the right password is not checked.
        $this->postJson('/staff', ['username' => 'kasir', 'password' => 'right-password'])
            ->assertStatus(423)
            ->assertJsonPath('prank.stage', 'paused');
        $this->assertGuest();
    }

    public function test_the_pause_grows_from_one_to_three_to_five_minutes_and_stops_there(): void
    {
        $pauses = [];

        for ($round = 0; $round < 4; $round++) {
            for ($i = 0; $i < 3; $i++) {
                $this->wrong('/superadmin');
            }
            $pauses[] = $this->wrong('/superadmin')->json('prank.retry_after');
            $this->travel(6)->minutes();
        }

        $this->assertSame([60, 180, 300, 300], $pauses);
    }

    public function test_the_pause_ends_on_its_own(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->wrong();
        }

        $this->travel(61)->seconds();

        $this->postJson('/staff', ['username' => 'kasir', 'password' => 'right-password'])->assertOk();
        $this->assertAuthenticated();
    }

    public function test_a_correct_sign_in_starts_the_count_again(): void
    {
        $this->wrong();
        $this->wrong();
        $this->postJson('/staff', ['username' => 'kasir', 'password' => 'right-password'])->assertOk();
        auth()->logout();

        $this->wrong()->assertJsonPath('prank.stage', 1);
    }

    public function test_a_paused_page_tells_the_script_how_long_is_left(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->wrong();
        }

        $this->get('/staff')->assertOk()->assertSee('data-prank-seconds="60"', false);
    }

    public function test_the_scenes_are_off_when_switched_off(): void
    {
        config(['auth.login_prank' => false]);

        for ($i = 0; $i < 5; $i++) {
            $this->wrong()->assertStatus(422)->assertJsonMissingPath('prank');
        }
    }
}

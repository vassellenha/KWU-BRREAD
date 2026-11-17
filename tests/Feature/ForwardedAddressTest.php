<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustOnlyTheProxyHop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ForwardedAddressTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function chains(): array
    {
        return [
            'one entry' => ['203.0.113.7', '203.0.113.7'],
            'a spoofed entry in front of the real one' => ['9.9.9.9, 203.0.113.7', '203.0.113.7'],
            'many spoofed entries' => ['1.1.1.1, 2.2.2.2, 3.3.3.3, 203.0.113.7', '203.0.113.7'],
            'the host keeps an internal hop last' => ['203.0.113.7, 100.64.0.9', '203.0.113.7'],
            'a private hop last' => ['9.9.9.9, 203.0.113.7, 10.0.0.4', '203.0.113.7'],
            'nothing public at all (local work)' => ['192.168.1.5', '192.168.1.5'],
            'padding and empty entries' => ['9.9.9.9,  , 203.0.113.7 ', '203.0.113.7'],
            'an ipv6 visitor' => ['9.9.9.9, 2606:4700::1111', '2606:4700::1111'],
        ];
    }

    #[DataProvider('chains')]
    public function test_only_the_address_the_edge_saw_is_used(string $chain, string $expected): void
    {
        $this->assertSame($expected, TrustOnlyTheProxyHop::visitor($chain));
    }

    public function test_a_forged_header_cannot_choose_the_address_a_request_is_counted_under(): void
    {
        Route::get('/__ip', fn (\Illuminate\Http\Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '100.64.0.5'])
            ->withHeader('X-Forwarded-For', '9.9.9.9, 203.0.113.7')
            ->get('/__ip')
            ->assertOk()
            ->assertSee('203.0.113.7')
            ->assertDontSee('9.9.9.9');
    }

    public function test_on_railway_the_address_the_edge_saw_wins_over_its_own_edge_hop(): void
    {
        Route::get('/__ip', fn (\Illuminate\Http\Request $request) => $request->ip());

        // What Railway really sends: the visitor first, its own Singapore edge last, X-Real-IP set.
        $this->withServerVariables(['REMOTE_ADDR' => '100.64.0.5'])
            ->withHeaders(['X-Forwarded-For' => '103.171.164.172, 152.233.15.120', 'X-Real-IP' => '103.171.164.172'])
            ->get('/__ip')
            ->assertSee('103.171.164.172')
            ->assertDontSee('152.233.15.120');
    }

    public function test_a_forged_chain_cannot_beat_the_edge_set_real_ip(): void
    {
        Route::get('/__ip', fn (\Illuminate\Http\Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '100.64.0.5'])
            ->withHeaders(['X-Forwarded-For' => '9.9.9.9, 103.171.164.172, 152.233.15.120', 'X-Real-IP' => '103.171.164.172'])
            ->get('/__ip')
            ->assertSee('103.171.164.172')
            ->assertDontSee('9.9.9.9');
    }
}

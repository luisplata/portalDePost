<?php

namespace Tests\Unit\Analytics;

use App\Services\AnalyticsTimeService;
use App\Services\PostAnalyticsService;
use Tests\TestCase;

/**
 * Identidad anonima y filtros puros (T-S2b-03, REQ-S2-03, REQ-S2-05).
 *
 * Sin request ni base: la identidad se resuelve con cookie valida o con
 * `ip:` + sha256(ip + "|" + app.key). Los bots, el UA vacio y el prefetch
 * nunca cuentan.
 */
class IdentityAndBotTest extends TestCase
{
    private function service(): PostAnalyticsService
    {
        return new PostAnalyticsService(new AnalyticsTimeService());
    }

    public function test_a_valid_cookie_is_the_identity_in_lowercase()
    {
        $identity = $this->service()->identity(
            '550E8400-E29B-41D4-A716-446655440000',
            '10.0.0.1'
        );

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $identity);
    }

    public function test_a_missing_cookie_falls_back_to_the_hashed_ip()
    {
        $expected = 'ip:'.hash('sha256', '10.0.0.1|'.config('app.key'));

        $this->assertSame($expected, $this->service()->identity(null, '10.0.0.1'));
    }

    public function test_an_invalid_cookie_falls_back_to_the_hashed_ip()
    {
        $expected = 'ip:'.hash('sha256', '10.0.0.1|'.config('app.key'));

        $this->assertSame($expected, $this->service()->identity('short', '10.0.0.1'));
        $this->assertSame($expected, $this->service()->identity('bad/value', '10.0.0.1'));
        $this->assertSame($expected, $this->service()->identity(str_repeat('a', 65), '10.0.0.1'));
    }

    public function test_the_same_ip_without_cookie_shares_identity()
    {
        $service = $this->service();

        $this->assertSame(
            $service->identity(null, '10.0.0.1'),
            $service->identity(null, '10.0.0.1')
        );
        $this->assertNotSame(
            $service->identity(null, '10.0.0.1'),
            $service->identity(null, '10.0.0.2')
        );
    }

    public function test_ip_hash_is_64_lowercase_hex()
    {
        $hash = $this->service()->ipHash('10.0.0.1');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(strtolower($hash), $hash);
        $this->assertSame(1, preg_match('/^[0-9a-f]{64}$/', $hash));
    }

    public function test_bots_empty_agents_and_bot_suffixes_are_detected()
    {
        $service = $this->service();

        $this->assertTrue($service->isBot(null));
        $this->assertTrue($service->isBot(''));
        $this->assertTrue($service->isBot('Googlebot/2.1'));
        $this->assertTrue($service->isBot('Mozilla/5.0 (compatible; Some_Bot)'));
        $this->assertTrue($service->isBot('python-requests/2.28'));
        $this->assertFalse($service->isBot('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120'));
    }

    public function test_prefetch_and_prerender_purposes_are_detected()
    {
        $service = $this->service();

        $this->assertTrue($service->isPrefetch('prefetch'));
        $this->assertTrue($service->isPrefetch('prefetch;prerender'));
        $this->assertTrue($service->isPrefetch('Prerender'));
        $this->assertFalse($service->isPrefetch(null));
        $this->assertFalse($service->isPrefetch(''));
    }
}

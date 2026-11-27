<?php

namespace Tests\Unit\Analytics;

use Tests\TestCase;

/**
 * Defaults de configuracion de analitica (T-S2a-01, NFR-01, S3-09, S3-10).
 *
 * Todo el codigo nuevo lee `config('analytics.*')`; nunca `env()` fuera de
 * config/. El deploy no activa `config:cache`, asi que los defaults tienen
 * que estar escritos de forma explicita.
 */
class AnalyticsConfigTest extends TestCase
{
    public function test_capture_is_disabled_by_default()
    {
        $this->assertIsBool(config('analytics.enabled'));
        $this->assertFalse(config('analytics.enabled'));
    }

    public function test_operational_timezone_is_bogota_and_app_timezone_stays_utc()
    {
        $this->assertSame('America/Bogota', config('analytics.timezone'));
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_retention_backfill_and_drift_defaults_are_explicit()
    {
        $this->assertSame(90, config('analytics.retention_days'));
        $this->assertSame(365, config('analytics.backfill_days'));
        $this->assertSame(5, config('analytics.drift_threshold_pct'));
        $this->assertSame(50, config('analytics.drift_min_volume'));
        $this->assertFalse(config('analytics.opportunistic_purge'));
    }

    public function test_bot_regex_matches_the_shared_ads_pattern()
    {
        $this->assertSame(config('ads.bot_ua_regex'), config('analytics.bot_ua_regex'));
        $this->assertSame(1, preg_match(config('analytics.bot_ua_regex'), 'Mozilla/5.0 Googlebot/2.1'));
    }
}

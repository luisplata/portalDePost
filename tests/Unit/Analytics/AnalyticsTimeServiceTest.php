<?php

namespace Tests\Unit\Analytics;

use App\Services\AnalyticsTimeService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fuente unica del dia operativo (T-S2b-01, REQ-S2-04, REQ-S3-06).
 *
 * America/Bogota es UTC-5 fijo. El crudo y Carbon::setTestNow quedan en UTC;
 * solo el dia de negocio cambia de zona.
 */
class AnalyticsTimeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_today_uses_the_local_day_at_the_utc_boundary()
    {
        $time = new AnalyticsTimeService();

        Carbon::setTestNow('2026-11-25 02:00:00');
        $this->assertSame('2026-11-24', $time->today());

        Carbon::setTestNow('2026-11-25 04:59:59');
        $this->assertSame('2026-11-24', $time->today());

        Carbon::setTestNow('2026-11-25 05:00:00');
        $this->assertSame('2026-11-25', $time->today());
    }

    public function test_to_local_date_converts_an_utc_instant()
    {
        $time = new AnalyticsTimeService();

        $this->assertSame('2026-11-24', $time->toLocalDate('2026-11-25 02:00:00'));
        $this->assertSame('2026-11-25', $time->toLocalDate('2026-11-25 05:00:00'));
        $this->assertSame('2026-11-10', $time->toLocalDate(Carbon::parse('2026-11-11 03:00:00', 'UTC')));
    }

    public function test_day_borders_are_utc_instants_of_the_local_midnight()
    {
        $time = new AnalyticsTimeService();

        $this->assertSame('2026-11-24 05:00:00', $time->dayStartUtc('2026-11-24')->toDateTimeString());
        $this->assertSame('2026-11-25 05:00:00', $time->dayEndUtc('2026-11-24')->toDateTimeString());
    }

    public function test_range_defaults_to_the_last_thirty_local_days()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');

        $range = (new AnalyticsTimeService())->range(null, null);

        $this->assertSame('2026-10-26', $range['from']);
        $this->assertSame('2026-11-24', $range['to']);
    }

    public function test_range_truncates_a_span_longer_than_365_inclusive_days()
    {
        $range = (new AnalyticsTimeService())->range('2024-01-01', '2026-01-01');

        $this->assertSame('2025-01-02', $range['from']);
        $this->assertSame('2026-01-01', $range['to']);
    }

    public function test_range_keeps_a_span_inside_the_limit()
    {
        $range = (new AnalyticsTimeService())->range('2026-11-01', '2026-11-24');

        $this->assertSame('2026-11-01', $range['from']);
        $this->assertSame('2026-11-24', $range['to']);
    }

    public function test_dates_returns_every_inclusive_day_in_order()
    {
        $dates = (new AnalyticsTimeService())->dates('2026-11-22', '2026-11-24');

        $this->assertSame(['2026-11-22', '2026-11-23', '2026-11-24'], $dates);
    }
}

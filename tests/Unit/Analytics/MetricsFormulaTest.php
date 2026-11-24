<?php

namespace Tests\Unit\Analytics;

use App\Services\AnalyticsTimeService;
use App\Services\PostMetricsService;
use Tests\TestCase;

/**
 * Formulas de conversion (T-S3a-01, REQ-S3-01, REQ-S3-02).
 *
 * La conversion principal es la interseccion por producto y nunca supera 100.
 * La conversion por pageview puede superarlo y es la unica comparable con el
 * numero legado.
 */
class MetricsFormulaTest extends TestCase
{
    private function service(): PostMetricsService
    {
        return new PostMetricsService(new AnalyticsTimeService());
    }

    public function test_conversion_principal_is_the_intersection_over_unique_visitors()
    {
        $service = $this->service();

        $this->assertSame(50.0, $service->conversionPrincipal(1, 2));
        $this->assertSame(0.0, $service->conversionPrincipal(0, 2));
        $this->assertSame(33.33, $service->conversionPrincipal(1, 3));
    }

    public function test_conversion_principal_is_null_without_denominator()
    {
        $service = $this->service();

        $this->assertNull($service->conversionPrincipal(0, 0));
        $this->assertNull($service->conversionPrincipal(5, 0));
        $this->assertNull($service->conversionPrincipal(null, 10));
        $this->assertNull($service->conversionPrincipal(3, null));
    }

    public function test_conversion_principal_never_exceeds_one_hundred()
    {
        $this->assertSame(100.0, $this->service()->conversionPrincipal(5, 2));
    }

    public function test_conversion_by_pageview_may_exceed_one_hundred()
    {
        $service = $this->service();

        $this->assertSame(120.0, $service->conversionByPageview(12, 10));
        $this->assertSame(5.0, $service->conversionByPageview(5, 100));
    }

    public function test_conversion_by_pageview_is_null_without_denominator_or_coverage()
    {
        $service = $this->service();

        $this->assertNull($service->conversionByPageview(0, 0));
        $this->assertNull($service->conversionByPageview(5, 0));
        $this->assertNull($service->conversionByPageview(null, 10));
    }

    public function test_clicks_per_visitor_rounds_to_two_decimals()
    {
        $service = $this->service();

        $this->assertSame(0.5, $service->clicksPerVisitor(2, 4));
        $this->assertSame(0.67, $service->clicksPerVisitor(2, 3));
        $this->assertNull($service->clicksPerVisitor(5, 0));
        $this->assertNull($service->clicksPerVisitor(null, 3));
    }
}

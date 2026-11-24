<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Panel admin de analitica (T-S4a-01..06, REQ-S4-01..03, S4-05, S4-08).
 *
 * La pagina solo normaliza filtros y renderiza; los datos llegan por un unico
 * fetch al endpoint. El item vive en el menu Graficas existente.
 */
class AdminPanelTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_panel_requires_a_session()
    {
        $this->get('/admin/analitica')->assertRedirect('/logout');
    }

    public function test_other_methods_are_not_allowed()
    {
        $this->asAdmin()->post('/admin/analitica')->assertStatus(405);
    }

    public function test_the_panel_renders_the_filters_ranking_and_legacy_block()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');

        $response = $this->asAdmin()->get('/admin/analitica')->assertStatus(200);

        $response->assertSee('Analitica de publicaciones');
        $response->assertSee('name="from"', false);
        $response->assertSee('name="to"', false);
        $response->assertSee('name="metric"', false);
        $response->assertSee('name="include_inactive"', false);

        $response->assertSee('Puesto');
        $response->assertSee('Publicacion');
        $response->assertSee('UV');
        $response->assertSee('Pageviews');
        $response->assertSee('Clicks');
        $response->assertSee('Conversion principal');
        $response->assertSee('Conversion por pageview');
        $response->assertSee('Clicks por visitante');
        $response->assertSee('sin dato', false);

        $response->assertSee('Acumulado historico');
        $response->assertSee('desde siempre, sin dedupe ni filtro de bots');
        $response->assertSee('visit_posts cuenta pageviews');
        $response->assertSee('admin/analitica/metricas', false);
        $response->assertSee('admin/graficas', false);
    }

    public function test_the_panel_links_from_the_existing_graficas_menu_without_breaking_it()
    {
        $response = $this->asAdmin()->get('/admin/producto')->assertStatus(200);

        $response->assertSee('admin/categoria', false);
        $response->assertSee('admin/producto', false);
        $response->assertSee('admin/stream', false);
        $response->assertSeeInOrder([
            'admin/graficas',
            'admin/analitica',
            'admin/publicidad',
        ], false);
    }

    public function test_rendering_the_panel_does_not_query_the_analytics_stores()
    {
        $analyticsQueries = [];

        DB::listen(function ($query) use (&$analyticsQueries) {
            if (preg_match('/(post_stats_daily|post_visit_identities|post_clicks|visit_posts)/', $query->sql)) {
                $analyticsQueries[] = $query->sql;
            }
        });

        $this->asAdmin()->get('/admin/analitica')->assertStatus(200);

        $this->assertSame([], $analyticsQueries, 'The panel must not query analytics stores on render.');
    }

    public function test_the_page_makes_a_single_fetch_to_the_metrics_endpoint()
    {
        $response = $this->asAdmin()->get('/admin/analitica')->assertStatus(200);

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'admin/analitica/metricas'));
        $this->assertSame(1, substr_count($html, 'fetch('));
    }

    public function test_filters_are_reflected_in_the_url_and_the_form()
    {
        $response = $this->asAdmin()
            ->get('/admin/analitica?from=2026-11-01&to=2026-11-10&metric=pageviews&include_inactive=1')
            ->assertStatus(200);

        $response->assertSee('value="2026-11-01"', false);
        $response->assertSee('value="2026-11-10"', false);
        $response->assertSee('value="pageviews" selected', false);
        $response->assertSee('name="include_inactive" value="1" checked', false);
    }

    public function test_invalid_filters_fall_back_to_the_defaults()
    {
        Carbon::setTestNow('2026-11-25 02:00:00');

        $response = $this->asAdmin()
            ->get('/admin/analitica?from=garbage&to=2026-13-01&metric=clicks')
            ->assertStatus(200);

        $response->assertSee('value="2026-10-26"', false);
        $response->assertSee('value="2026-11-24"', false);
        $response->assertSee('value="uv" selected', false);
    }
}

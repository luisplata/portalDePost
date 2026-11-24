<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Caracterizacion de los contadores legacy (REG-02, REG-07).
 *
 * La ficha suma 1 a `visit_posts.visita` y crea una fila en `log_visits`.
 * Los redirects devuelven 302 al `hotLink` y suman 1 a `visit_posts.idoAlPack`.
 * Este comportamiento no puede cambiar cuando se instrumente la analitica.
 */
class LegacyCounterTest extends AnalyticsTestSupport
{
    use RefreshDatabase;

    public function test_content_page_increments_legacy_counters_once()
    {
        $productoId = $this->makeProduct(['nombre' => 'Legacy Content']);

        $this->get('/content/Legacy-Content')->assertStatus(200);

        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('visita')
        );
        $this->assertSame(
            1,
            DB::table('log_visits')->where('producto_id', $productoId)->count()
        );
    }

    public function test_redirect_by_id_returns_302_to_the_hotlink_and_counts_the_click()
    {
        $productoId = $this->makeProduct([
            'nombre' => 'Legacy Redirect',
            'hotLink' => 'https://example.com/legacy-id',
        ]);

        $response = $this->get('/redirect/'.$productoId);

        $response->assertStatus(302);
        $this->assertSame('https://example.com/legacy-id', $response->headers->get('Location'));
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );
    }

    public function test_redirect_by_name_returns_302_to_the_hotlink_and_counts_the_click()
    {
        $productoId = $this->makeProduct([
            'nombre' => 'LegacyName',
            'hotLink' => 'https://example.com/legacy-name',
        ]);

        $response = $this->get('/redirection/LegacyName');

        $response->assertStatus(302);
        $this->assertSame('https://example.com/legacy-name', $response->headers->get('Location'));
        $this->assertSame(
            1,
            (int) DB::table('visit_posts')->where('producto_id', $productoId)->value('idoAlPack')
        );
    }
}

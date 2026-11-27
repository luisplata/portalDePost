<?php

namespace Tests\Feature\Publicacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Confirmacion explicita del reintento (FIX-7, REQ-OBS-04).
 */
class EntregasConfirmacionTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    public function test_retry_confirmation_is_rendered_only_for_uncertain_or_sent()
    {
        $canal = $this->makeCanal('telegram');
        $fallido = $this->makeProducto();
        $incierto = $this->makeProducto();
        $this->makeEnvio($fallido, $canal->id, ['estado' => 'fallido']);
        $this->makeEnvio($incierto, $canal->id, ['estado' => 'incierto']);

        $sinConfirmacion = $this->asAdmin()->get('/admin/publicaciones?producto='.$fallido);
        $sinConfirmacion->assertStatus(200);
        $sinConfirmacion->assertDontSee('confirm(', false);

        $conConfirmacion = $this->asAdmin()->get('/admin/publicaciones?producto='.$incierto);
        $conConfirmacion->assertStatus(200);
        $conConfirmacion->assertSee('confirm(', false);
        $conConfirmacion->assertSee('duplicado');
    }
}

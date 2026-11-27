<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionCanal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Vistas del admin de canales: mascaras, XSS, CSRF y menu (T-S6-06/07/08,
 * REQ-SEG-02, SEG-03, SEG-08, CAN-13, OBS-01).
 */
class CanalAdminViewTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

    public function test_index_lists_channels_with_mask_and_without_the_real_token()
    {
        $primero = $this->makeCanal('telegram', ['nombre' => 'Alfa']);
        $this->makeCanal('discord', ['nombre' => 'Beta']);

        $respuesta = $this->asAdmin()->get('/admin/canales');

        $respuesta->assertStatus(200);
        $respuesta->assertSeeInOrder(['Alfa', 'Beta']);
        $respuesta->assertSee('123456789:****');
        $respuesta->assertDontSee(self::TOKEN);
        $respuesta->assertDontSee('abcdEFGH_-123');
        $respuesta->assertSee('name="_token"', false);
        $respuesta->assertSee(url('admin/canales/'.$primero->id.'/edit'), false);
    }

    public function test_create_form_offers_only_the_driver_map_keys()
    {
        $respuesta = $this->asAdmin()->get('/admin/canales/create');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('name="tipo"', false);
        $respuesta->assertSee('value="telegram"', false);
        $respuesta->assertSee('value="discord"', false);
        $respuesta->assertDontSee('mastodon');
        $respuesta->assertSee('name="_token"', false);
        $respuesta->assertDontSee('name="vigente_desde"', false);
    }

    public function test_edit_uses_placeholder_and_never_the_secret_value()
    {
        $canal = $this->makeCanal('telegram');

        $respuesta = $this->asAdmin()->get('/admin/canales/'.$canal->id.'/edit');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('123456789:****');
        $respuesta->assertDontSee(self::TOKEN);
        $respuesta->assertDontSee('value="123456789');
        $respuesta->assertSee('name="token"', false);
        $respuesta->assertSee('name="chat_id"', false);
        $respuesta->assertSee('name="vigente_desde"', false);
        $respuesta->assertSee('name="_token"', false);
    }

    public function test_discord_edit_masks_the_webhook_until_the_last_slash()
    {
        $canal = $this->makeCanal('discord');

        $respuesta = $this->asAdmin()->get('/admin/canales/'.$canal->id.'/edit');

        $respuesta->assertSee('https://discord.com/api/webhooks/1234/****');
        $respuesta->assertDontSee('abcdEFGH_-123');
    }

    public function test_vigencia_warning_is_visible_on_edit()
    {
        $canal = $this->makeCanal('telegram');

        $this->asAdmin()->get('/admin/canales/'.$canal->id.'/edit')
            ->assertSee('puede generar un lote grande de envios');
    }

    public function test_channel_data_is_escaped_in_the_index()
    {
        PublicacionCanal::create([
            'tipo' => 'telegram',
            'nombre' => '<script>alert(1)</script>',
            'habilitado' => 0,
            'vigente_desde' => now(),
            'credenciales' => ['token' => self::TOKEN, 'chat_id' => '-1'],
            'estado' => 'invalido_credencial',
            'estado_detalle' => '<img src=x onerror=alert(1)>',
        ]);

        $respuesta = $this->asAdmin()->get('/admin/canales');

        $respuesta->assertDontSee('<script>alert(1)</script>', false);
        $respuesta->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $respuesta->assertSee('invalido_credencial');
    }

    public function test_menu_adds_canales_under_publicaciones_without_breaking_existing_items()
    {
        $respuesta = $this->asAdmin()->get('/admin/producto');

        $respuesta->assertStatus(200);
        $respuesta->assertSee(url('admin/canales'), false);
        $respuesta->assertSeeInOrder([
            'admin',
            'admin/categoria',
            'admin/producto',
            'admin/stream',
            'admin/graficas',
            'admin/publicidad',
        ], false);
        $respuesta->assertSee('Publicaciones');
        $respuesta->assertSee('Canales');
    }
}

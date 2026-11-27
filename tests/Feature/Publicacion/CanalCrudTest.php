<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionCanal;
use App\PublicacionEnvio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * CRUD y reglas de canales desde el admin (T-S6-01..05, 08, REQ-CAN-01..13,
 * SEG-02, SEG-04, SEG-07, OBS-08).
 */
class CanalCrudTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_channel_routes_redirect_to_logout_without_session()
    {
        $this->get('/admin/canales')->assertRedirect('/logout');
        $this->post('/admin/canales', [])->assertRedirect('/logout');
        $this->get('/admin/canales/create')->assertRedirect('/logout');
    }

    public function test_store_creates_the_channel_with_now_and_ignores_posted_vigencia()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        $this->fakeEspera();

        $this->asAdmin()->post('/admin/canales', $this->payload([
            'vigente_desde' => '2025-01-01T00:00',
        ]))->assertRedirect(url('admin/canales'));

        $canal = PublicacionCanal::first();

        $this->assertSame('Telegram principal', $canal->nombre);
        $this->assertSame('telegram', $canal->tipo);
        $this->assertSame(0, (int) $canal->habilitado);
        $this->assertSame('ok', $canal->estado);
        $this->assertSame('2026-11-25 10:00:00', $canal->vigente_desde->toDateTimeString());
        $this->assertSame(self::TOKEN, $canal->credenciales['token']);
    }

    public function test_store_rejects_duplicate_name_even_after_soft_delete()
    {
        $canal = $this->makeCanal('telegram', ['nombre' => 'Telegram principal']);
        $canal->delete();

        $respuesta = $this->asAdmin()->post('/admin/canales', $this->payload());

        $respuesta->assertSessionHasErrors('nombre');
        $this->assertSame('Ya existe un canal con ese nombre.', session('errors')->first('nombre'));
        $this->assertSame(1, DB::table('publicacion_canales')->count());
    }

    public function test_store_rejects_invalid_credentials_without_http()
    {
        Http::fake();

        $respuesta = $this->asAdmin()->post('/admin/canales', $this->payload([
            'token' => 'no-es-token',
        ]));

        $respuesta->assertSessionHasErrors('token');
        $this->assertSame('El token de Telegram no tiene un formato valido.', session('errors')->first('token'));
        $this->assertSame(0, DB::table('publicacion_canales')->count());
        Http::assertNothingSent();
    }

    public function test_store_rejects_partial_telegram_credentials()
    {
        $respuesta = $this->asAdmin()->post('/admin/canales', $this->payload([
            'chat_id' => '',
        ]));

        $respuesta->assertSessionHasErrors('token');
        $this->assertSame(
            'Las credenciales de Telegram requieren token y chat_id juntos.',
            session('errors')->first('token')
        );
    }

    public function test_store_rejects_a_discord_webhook_outside_the_allowed_hosts()
    {
        $respuesta = $this->asAdmin()->post('/admin/canales', [
            'tipo' => 'discord',
            'nombre' => 'Discord ajeno',
            'webhook_url' => 'https://evil.example/api/webhooks/1/abc',
        ]);

        $respuesta->assertSessionHasErrors('webhook_url');
        $this->assertSame(
            'La URL del webhook de Discord debe ser https en discord.com o discordapp.com.',
            session('errors')->first('webhook_url')
        );
        $this->assertSame(0, DB::table('publicacion_canales')->count());
    }

    public function test_store_rejects_a_name_longer_than_sixty()
    {
        $respuesta = $this->asAdmin()->post('/admin/canales', $this->payload([
            'nombre' => str_repeat('a', 61),
        ]));

        $respuesta->assertSessionHasErrors('nombre');
        $this->assertSame(
            'El nombre no puede superar los 60 caracteres.',
            session('errors')->first('nombre')
        );
    }

    public function test_update_keeps_secrets_and_vigencia_when_fields_are_empty()
    {
        $canal = $this->makeCanal('telegram', [
            'nombre' => 'Telegram original',
            'vigente_desde' => '2026-11-20 09:00:00',
        ]);

        $this->asAdmin()->put('/admin/canales/'.$canal->id, [
            'nombre' => 'Telegram renombrado',
            'token' => '',
            'chat_id' => '',
            'vigente_desde' => '',
        ])->assertRedirect(url('admin/canales'));

        $fresco = PublicacionCanal::find($canal->id);

        $this->assertSame('Telegram renombrado', $fresco->nombre);
        $this->assertSame(self::TOKEN, $fresco->credenciales['token']);
        $this->assertSame('-1001234567890', $fresco->credenciales['chat_id']);
        $this->assertSame('2026-11-20 09:00:00', $fresco->vigente_desde->toDateTimeString());
    }

    public function test_update_rejects_a_type_change()
    {
        $canal = $this->makeCanal('telegram');

        $respuesta = $this->asAdmin()->put('/admin/canales/'.$canal->id, [
            'tipo' => 'discord',
            'nombre' => $canal->nombre,
            'webhook_url' => 'https://discord.com/api/webhooks/1/abc',
        ]);

        $respuesta->assertSessionHasErrors('tipo');
        $this->assertSame(
            'El tipo de un canal no se puede cambiar.',
            session('errors')->first('tipo')
        );
        $this->assertSame('telegram', $canal->fresh()->tipo);
    }

    public function test_toggle_resets_vigencia_only_on_zero_to_one()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        $canal = $this->makeCanal('telegram', [
            'habilitado' => 0,
            'vigente_desde' => '2026-11-20 09:00:00',
        ]);

        $this->asAdmin()->post('/admin/canales/'.$canal->id.'/estado', ['habilitado' => 1])
            ->assertRedirect(url('admin/canales'));
        $this->assertSame('2026-11-25 10:00:00', $canal->fresh()->vigente_desde->toDateTimeString());

        Carbon::setTestNow('2026-11-26 12:00:00');
        $this->asAdmin()->post('/admin/canales/'.$canal->id.'/estado', ['habilitado' => 1]);
        $this->assertSame('2026-11-25 10:00:00', $canal->fresh()->vigente_desde->toDateTimeString());

        $this->asAdmin()->post('/admin/canales/'.$canal->id.'/estado', ['habilitado' => 0]);
        $this->assertSame('2026-11-25 10:00:00', $canal->fresh()->vigente_desde->toDateTimeString());

        $this->asAdmin()->post('/admin/canales/'.$canal->id.'/estado', ['habilitado' => 0]);
        $this->assertSame('2026-11-25 10:00:00', $canal->fresh()->vigente_desde->toDateTimeString());
    }

    public function test_destroy_is_soft_and_keeps_the_delivery_history()
    {
        $canal = $this->makeCanal('telegram');
        $producto = $this->makeProducto();
        $envio = $this->makeEnvio($producto, $canal->id);

        $this->asAdmin()->delete('/admin/canales/'.$canal->id)
            ->assertRedirect(url('admin/canales'));

        $this->assertSame(1, DB::table('publicacion_canales')->count());
        $this->assertNotNull(DB::table('publicacion_canales')->value('deleted_at'));
        $this->assertSame($canal->id, $envio->fresh()->publicacion_canal_id);
        $this->assertNull(PublicacionCanal::find($canal->id));
    }

    public function test_probe_sends_text_without_creating_deliveries()
    {
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 1],
        ], 200)]);
        $canal = $this->makeCanal('telegram');

        $this->asAdmin()->post('/admin/canales/'.$canal->id.'/probar')
            ->assertRedirect(url('admin/canales'));

        $this->assertSame(0, DB::table('publicacion_envios')->count());
        $this->assertSame('ok', $canal->fresh()->estado);
        $this->assertNotNull($canal->fresh()->estado_verificado_at);
        Http::assertSentCount(1);
    }

    public function test_probe_401_marks_credentials_invalid_without_deliveries()
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);
        $canal = $this->makeCanal('telegram');

        $this->asAdmin()->post('/admin/canales/'.$canal->id.'/probar');

        $this->assertSame('invalido_credencial', $canal->fresh()->estado);
        $this->assertSame(0, DB::table('publicacion_envios')->count());
    }

    public function test_audit_logs_field_names_and_never_secret_values()
    {
        Log::spy();

        $this->asAdmin()
            ->withSession(['admin' => (object) [
                'id' => 7,
                'email' => 'admin@test.local',
                'nombre' => 'tester',
            ]])
            ->post('/admin/canales', $this->payload());

        Log::shouldHaveReceived('info')->withArgs(function ($mensaje, $contexto) {
            return $mensaje === 'publicacion.auditoria'
                && $contexto['recurso'] === 'canal'
                && $contexto['accion'] === 'alta'
                && in_array('credenciales', $contexto['campos'], true)
                && strpos(json_encode($contexto), self::TOKEN) === false;
        })->once();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tipo' => 'telegram',
            'nombre' => 'Telegram principal',
            'habilitado' => '0',
            'token' => self::TOKEN,
            'chat_id' => '-1001234567890',
        ], $overrides);
    }

    public function test_editing_vigencia_audits_the_previous_and_new_values()
    {
        Log::spy();
        $canal = $this->makeCanal('telegram', ['vigente_desde' => '2026-11-20 09:00:00']);

        $this->asAdmin()
            ->withSession(['admin' => (object) ['id' => 5, 'email' => 'admin@test.local', 'nombre' => 'tester']])
            ->put('/admin/canales/'.$canal->id, [
                'nombre' => $canal->nombre,
                'token' => '',
                'chat_id' => '',
                'vigente_desde' => '2026-10-01T08:30',
            ]);

        Log::shouldHaveReceived('info')->withArgs(function ($mensaje, $contexto) {
            return $mensaje === 'publicacion.auditoria'
                && $contexto['accion'] === 'edicion'
                && in_array('vigente_desde', $contexto['campos'], true)
                && $contexto['antes']['vigente_desde'] === '2026-11-20 09:00:00'
                && $contexto['despues']['vigente_desde'] === '2026-10-01 08:30:00';
        })->once();
    }
}

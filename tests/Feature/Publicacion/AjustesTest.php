<?php

namespace Tests\Feature\Publicacion;

use App\PublicacionAjuste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Publicacion\PublicacionTestSupport;

/**
 * Accesores fail-closed de publicacion_ajustes (T-S1-05, REQ-AJUS-01..03, 06,
 * OBS-07).
 */
class AjustesTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_missing_settings_are_fail_closed()
    {
        $this->assertFalse(PublicacionAjuste::activo());
        $this->assertNull(PublicacionAjuste::desde());

        $resumen = PublicacionAjuste::resumenCorrida();

        $this->assertNull($resumen['corrida_at']);
        $this->assertSame(0, $resumen['sembradas']);
        $this->assertSame(0, $resumen['enviadas']);
        $this->assertSame(0, $resumen['fallidas']);
        $this->assertSame(0, $resumen['inciertas']);
        $this->assertSame(0, $resumen['duracion_ms']);
    }

    public function test_settings_are_read_from_the_database_on_every_call()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');

        PublicacionAjuste::poner('publicaciones_enabled', '1');
        PublicacionAjuste::poner('publicaciones_desde', '2026-11-20 08:30:00');

        $this->assertTrue(PublicacionAjuste::activo());
        $this->assertSame(
            '2026-11-20 08:30:00',
            PublicacionAjuste::desde()->toDateTimeString()
        );

        PublicacionAjuste::poner('publicaciones_enabled', '0');

        $this->assertFalse(PublicacionAjuste::activo());
        $this->assertSame(1, DB::table('publicacion_ajustes')
            ->where('clave', 'publicaciones_enabled')->count());
    }

    public function test_an_invalid_cutoff_is_read_as_null()
    {
        PublicacionAjuste::poner('publicaciones_desde', 'ayer');
        $this->assertNull(PublicacionAjuste::desde());

        PublicacionAjuste::poner('publicaciones_desde', '');
        $this->assertNull(PublicacionAjuste::desde());
    }

    public function test_seeder_does_not_overwrite_existing_values()
    {
        config([
            'publicaciones.sembrado_enabled' => true,
            'publicaciones.sembrado_desde' => '2026-01-01 00:00:00',
        ]);

        $this->artisan('db:seed', [
            '--class' => \Database\Seeders\PublicacionAjusteSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(PublicacionAjuste::activo());
        $this->assertSame(
            '2026-01-01 00:00:00',
            PublicacionAjuste::desde()->toDateTimeString()
        );

        PublicacionAjuste::poner('publicaciones_enabled', '0');
        PublicacionAjuste::poner('publicaciones_desde', '2026-06-01 12:00:00');

        $this->artisan('db:seed', [
            '--class' => \Database\Seeders\PublicacionAjusteSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFalse(PublicacionAjuste::activo());
        $this->assertSame(
            '2026-06-01 12:00:00',
            PublicacionAjuste::desde()->toDateTimeString()
        );
        $this->assertSame(2, DB::table('publicacion_ajustes')->count());
    }

    public function test_poner_upserts_by_clave_and_registrar_corrida_writes_the_summary()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');

        PublicacionAjuste::poner('publicaciones_enabled', '0');
        PublicacionAjuste::poner('publicaciones_enabled', '1');

        $this->assertSame('1', DB::table('publicacion_ajustes')
            ->where('clave', 'publicaciones_enabled')->value('valor'));
        $this->assertSame(1, DB::table('publicacion_ajustes')->count());

        PublicacionAjuste::registrarCorrida([
            'corrida_at' => now(),
            'corrida_sembradas' => 4,
            'corrida_enviadas' => 3,
            'corrida_fallidas' => 1,
            'corrida_inciertas' => 0,
            'corrida_duracion_ms' => 1234,
        ]);

        $resumen = PublicacionAjuste::resumenCorrida();

        $this->assertSame('2026-11-25 10:00:00', $resumen['corrida_at']->toDateTimeString());
        $this->assertSame(4, $resumen['sembradas']);
        $this->assertSame(3, $resumen['enviadas']);
        $this->assertSame(1, $resumen['fallidas']);
        $this->assertSame(0, $resumen['inciertas']);
        $this->assertSame(1234, $resumen['duracion_ms']);
    }

    public function test_settings_panel_shows_switch_cutoff_and_last_run()
    {
        $this->asAdmin()->get('/admin/publicaciones/ajustes')
            ->assertStatus(200)
            ->assertSee('name="publicaciones_enabled"', false)
            ->assertSee('name="publicaciones_desde"', false)
            ->assertSee('Sin ejecuciones')
            ->assertSee('name="_token"', false);

        PublicacionAjuste::registrarCorrida([
            'corrida_at' => now(),
            'corrida_sembradas' => 4,
            'corrida_enviadas' => 3,
            'corrida_fallidas' => 1,
            'corrida_inciertas' => 0,
            'corrida_duracion_ms' => 900,
        ]);

        $this->asAdmin()->get('/admin/publicaciones/ajustes')
            ->assertSee('4')
            ->assertSee('3')
            ->assertSee('1');
    }

    public function test_settings_panel_rejects_enabling_without_cutoff()
    {
        $this->asAdmin()->put('/admin/publicaciones/ajustes', [
            'publicaciones_enabled' => '1',
            'publicaciones_desde' => '',
        ])->assertSessionHasErrors('publicaciones_desde');

        $this->assertSame(
            'Para encender las publicaciones hay que indicar la fecha de corte.',
            session('errors')->first('publicaciones_desde')
        );
        $this->assertFalse(PublicacionAjuste::activo());
    }

    public function test_disabling_keeps_the_previous_cutoff()
    {
        PublicacionAjuste::poner('publicaciones_enabled', '1');
        PublicacionAjuste::poner('publicaciones_desde', '2026-11-20 08:30:00');

        $this->asAdmin()->put('/admin/publicaciones/ajustes', [
            'publicaciones_enabled' => '0',
            'publicaciones_desde' => '',
        ])->assertRedirect(url('admin/publicaciones/ajustes'));

        $this->assertFalse(PublicacionAjuste::activo());
        $this->assertSame('2026-11-20 08:30:00', PublicacionAjuste::desde()->toDateTimeString());
    }

    public function test_changing_settings_applies_on_the_next_run_without_sending()
    {
        Http::fake();
        $canal = $this->makeCanal('telegram');
        $this->makeProducto();
        $this->fakeEspera();

        $this->asAdmin()->put('/admin/publicaciones/ajustes', [
            'publicaciones_enabled' => '1',
            'publicaciones_desde' => '2026-11-20T08:30',
        ])->assertRedirect(url('admin/publicaciones/ajustes'));

        $this->assertTrue(PublicacionAjuste::activo());
        $this->assertSame('2026-11-20 08:30:00', PublicacionAjuste::desde()->toDateTimeString());
        $this->assertSame(0, DB::table('publicacion_envios')->count());
        Http::assertNothingSent();
    }

    public function test_settings_routes_are_not_captured_by_the_deliveries_wildcard()
    {
        $this->asAdmin()->get('/admin/publicaciones/ajustes')
            ->assertStatus(200)
            ->assertSee('Ajustes de publicacion');

        $this->asAdmin()->get('/admin/publicaciones')->assertSee('Entregas a redes');
    }

    public function test_settings_audit_uses_field_names_only()
    {
        Log::spy();

        $this->asAdmin()
            ->withSession(['admin' => (object) ['id' => 9, 'email' => 'admin@test.local', 'nombre' => 'tester']])
            ->put('/admin/publicaciones/ajustes', [
                'publicaciones_enabled' => '1',
                'publicaciones_desde' => '2026-11-20T08:30',
            ]);

        Log::shouldHaveReceived('info')->withArgs(function ($mensaje, $contexto) {
            return $mensaje === 'publicacion.auditoria'
                && $contexto['recurso'] === 'ajustes'
                && in_array('publicaciones_desde', $contexto['campos'], true)
                && $contexto['admin_email'] === 'admin@test.local';
        })->once();
    }
}

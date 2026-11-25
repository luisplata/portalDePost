<?php

namespace Tests\Feature\Publicacion;

use App\Services\Publicacion\PublicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Recalculo de published por aplicables y enviados (T-S4-05, REQ-ENT-10,
 * ENT-11, NFR-07).
 */
class ResumenPublishedTest extends PublicacionTestSupport
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_published_stays_zero_until_every_applicable_channel_is_sent()
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        $discordStatus = 500;

        Http::fake(function ($request) use (&$discordStatus) {
            if (strpos($request->url(), 'discord.com') !== false) {
                return Http::response('', $discordStatus);
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200);
        });

        $this->makeAjustes();
        $this->makeCanal('telegram');
        $this->makeCanal('discord');
        $producto = $this->makeProducto();
        $this->fakeEspera();

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(0, (int) DB::table('productos')->where('id', $producto)->value('published'));
        $this->assertSame(1, DB::table('publicacion_envios')->where('estado', 'pendiente')->count());

        Carbon::setTestNow('2026-11-25 10:05:00');
        $discordStatus = 204;
        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(1, (int) DB::table('productos')->where('id', $producto)->value('published'));
    }

    public function test_no_applicable_channel_does_not_touch_published()
    {
        $this->fakeHttpOk();
        $this->makeAjustes(['publicaciones_desde' => now()->subDays(60)->toDateTimeString()]);
        $this->makeCanal('telegram', ['vigente_desde' => now()]);
        $this->fakeEspera();

        $cero = $this->makeProducto([
            'created_at' => now()->subDays(30),
            'publication_date' => now()->subDays(30),
            'published' => 0,
        ]);
        $uno = $this->makeProducto([
            'created_at' => now()->subDays(30),
            'publication_date' => now()->subDays(30),
            'published' => 1,
        ]);

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(0, (int) DB::table('productos')->where('id', $cero)->value('published'));
        $this->assertSame(1, (int) DB::table('productos')->where('id', $uno)->value('published'));
        Http::assertNothingSent();
    }

    public function test_an_invalid_channel_does_not_count_as_applicable()
    {
        $this->fakeHttpOk();
        $this->makeAjustes();
        $canal = $this->makeCanal('telegram', ['estado' => 'invalido_credencial']);
        $producto = $this->makeProducto();
        $this->makeEnvio($producto, $canal->id);
        $this->fakeEspera();

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(0, (int) DB::table('productos')->where('id', $producto)->value('published'));
        Http::assertNothingSent();
    }

    public function test_a_channel_created_after_the_product_does_not_keep_published_at_zero()
    {
        $this->fakeHttpOk();
        $this->makeAjustes(['publicaciones_desde' => now()->subDays(60)->toDateTimeString()]);
        $canalViejo = $this->makeCanal('telegram', ['vigente_desde' => now()->subDays(30)]);
        $this->makeCanal('discord', ['vigente_desde' => now()]);
        $producto = $this->makeProducto([
            'created_at' => now()->subDays(10),
            'publication_date' => now()->subDays(10),
        ]);
        $this->makeEnvio($producto, $canalViejo->id);
        $this->fakeEspera();

        $this->app->make(PublicacionService::class)->corrida();

        $this->assertSame(1, (int) DB::table('productos')->where('id', $producto)->value('published'));
    }
}

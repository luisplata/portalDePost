<?php

namespace Tests\Unit\Publicacion;

use App\Services\Publicacion\Credenciales;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Validacion de formato sin red al guardar (T-S2-02, REQ-CAN-10, SEG-07).
 */
class CredencialesTest extends TestCase
{
    private const TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

    public function test_validates_telegram_and_discord_formats_without_http()
    {
        Http::fake();

        $telegram = Credenciales::validar('telegram', [
            'token' => self::TOKEN,
            'chat_id' => '-1001234567890',
        ]);

        $this->assertTrue($telegram['ok']);
        $this->assertSame('ok', $telegram['estado']);
        $this->assertNull($telegram['detalle']);

        $discord = Credenciales::validar('discord', [
            'webhook_url' => 'https://discord.com/api/webhooks/1234/abcdEFGH_-123',
        ]);

        $this->assertTrue($discord['ok']);
        $this->assertSame('ok', $discord['estado']);

        Http::assertNothingSent();
    }

    public function test_rejects_an_invalid_telegram_token()
    {
        $resultado = Credenciales::validar('telegram', [
            'token' => 'no-es-un-token',
            'chat_id' => '-1001234567890',
        ]);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('invalido_config', $resultado['estado']);
        $this->assertSame('El token de Telegram no tiene un formato valido.', $resultado['detalle']);
    }

    public function test_rejects_an_invalid_telegram_chat_id()
    {
        $resultado = Credenciales::validar('telegram', [
            'token' => self::TOKEN,
            'chat_id' => 'chat-invalido',
        ]);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('El chat_id de Telegram no tiene un formato valido.', $resultado['detalle']);
    }

    public function test_telegram_requires_token_and_chat_id_together()
    {
        $soloToken = Credenciales::validar('telegram', ['token' => self::TOKEN]);
        $soloChat = Credenciales::validar('telegram', ['chat_id' => '-1001234567890']);

        $this->assertFalse($soloToken['ok']);
        $this->assertSame(
            'Las credenciales de Telegram requieren token y chat_id juntos.',
            $soloToken['detalle']
        );
        $this->assertSame($soloToken['detalle'], $soloChat['detalle']);
    }

    public function test_rejects_a_discord_webhook_outside_the_allowed_hosts()
    {
        foreach ([
            'http://discord.com/api/webhooks/1234/abc',
            'https://evil.example/api/webhooks/1234/abc',
            'https://discord.com/otro/1234/abc',
        ] as $url) {
            $resultado = Credenciales::validar('discord', ['webhook_url' => $url]);

            $this->assertFalse($resultado['ok'], $url);
            $this->assertSame(
                'La URL del webhook de Discord debe ser https en discord.com o discordapp.com.',
                $resultado['detalle']
            );
        }
    }

    public function test_rejects_an_unknown_channel_type()
    {
        $resultado = Credenciales::validar('mastodon', ['token' => self::TOKEN]);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('invalido_config', $resultado['estado']);
        $this->assertSame('Tipo de canal desconocido.', $resultado['detalle']);
    }
}

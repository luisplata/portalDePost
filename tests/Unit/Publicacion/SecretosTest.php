<?php

namespace Tests\Unit\Publicacion;

use App\PublicacionCanal;
use App\Services\Publicacion\Secretos;
use Illuminate\Encryption\Encrypter;
use Tests\TestCase;

/**
 * Cifrado, mascara y redaccion de credenciales (T-S2-01, REQ-SEG-01, SEG-03,
 * SEG-04, SEG-05).
 */
class SecretosTest extends TestCase
{
    public function test_masks_telegram_tokens_and_discord_webhooks()
    {
        $token = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';
        $webhook = 'https://discord.com/api/webhooks/1234/abcdEFGH_-123';

        $this->assertSame('123456789:****', Secretos::mascara($token));
        $this->assertSame(
            'https://discord.com/api/webhooks/1234/****',
            Secretos::mascara($webhook)
        );
        $this->assertSame('****', Secretos::mascara('sin-separadores'));
    }

    public function test_redactar_removes_the_secret_and_truncates_to_500_with_ellipsis()
    {
        $token = '123456789:'.str_repeat('A', 40);
        $texto = 'fallo con '.$token.' contexto '.str_repeat('x', 900);

        $redactado = Secretos::redactar($texto, [$token]);

        $this->assertStringNotContainsString($token, $redactado);
        $this->assertStringNotContainsString(str_repeat('A', 40), $redactado);
        $this->assertLessThanOrEqual(500, strlen($redactado));
        $this->assertStringEndsWith('...', $redactado);
    }

    public function test_short_errors_are_not_truncated_but_are_redacted()
    {
        $webhook = 'https://discord.com/api/webhooks/9876/secreto';

        $redactado = Secretos::redactar('401 con '.$webhook, [$webhook]);

        $this->assertSame('401 con ****', $redactado);
    }

    public function test_descifrar_returns_the_array_or_null_without_throwing()
    {
        $credenciales = [
            'token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw',
            'chat_id' => '-1001234567890',
        ];

        $canal = new PublicacionCanal(['credenciales' => $credenciales]);

        $this->assertSame($credenciales, Secretos::descifrar($canal));

        $otraClave = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $ilegible = new PublicacionCanal();
        $ilegible->setRawAttributes([
            'credenciales' => $otraClave->encrypt(json_encode($credenciales)),
        ], true);

        $this->assertNull(Secretos::descifrar($ilegible));
    }
}

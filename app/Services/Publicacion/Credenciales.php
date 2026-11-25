<?php

namespace App\Services\Publicacion;

/**
 * Validacion pura de credenciales de canal (REQ-CAN-10, SEG-07).
 *
 * No hace red: el guardado rechaza formatos invalidos sin llamar al
 * proveedor. Los mensajes son contractuales.
 */
class Credenciales
{
    /**
     * @return array{ok: bool, estado: string, detalle: string|null}
     */
    public static function validar(string $tipo, array $credenciales): array
    {
        if ($tipo === 'telegram') {
            return self::telegram($credenciales);
        }

        if ($tipo === 'discord') {
            return self::discord($credenciales);
        }

        return self::invalido('Tipo de canal desconocido.');
    }

    private static function telegram(array $credenciales): array
    {
        $token = isset($credenciales['token']) ? (string) $credenciales['token'] : '';
        $chatId = isset($credenciales['chat_id']) ? (string) $credenciales['chat_id'] : '';

        $hayToken = trim($token) !== '';
        $hayChat = trim($chatId) !== '';

        if (!$hayToken || !$hayChat) {
            return self::invalido(
                'Las credenciales de Telegram requieren token y chat_id juntos.'
            );
        }

        if (!preg_match('/^[0-9]{6,}:[A-Za-z0-9_-]{30,60}$/', $token)) {
            return self::invalido('El token de Telegram no tiene un formato valido.');
        }

        if (!preg_match('/^(-?[0-9]{1,20}|@[A-Za-z0-9_]{5,32})$/', $chatId)) {
            return self::invalido('El chat_id de Telegram no tiene un formato valido.');
        }

        return self::valido();
    }

    private static function discord(array $credenciales): array
    {
        $url = isset($credenciales['webhook_url'])
            ? (string) $credenciales['webhook_url']
            : '';

        $mensaje = 'La URL del webhook de Discord debe ser https en discord.com o discordapp.com.';

        if ($url === '' || strlen($url) > 255) {
            return self::invalido($mensaje);
        }

        $partes = parse_url($url);

        if (!is_array($partes)
            || !isset($partes['scheme'], $partes['host'], $partes['path'])
            || strtolower($partes['scheme']) !== 'https'
            || !in_array(strtolower($partes['host']), ['discord.com', 'discordapp.com'], true)
        ) {
            return self::invalido($mensaje);
        }

        if (!preg_match('#^/api/webhooks/[0-9]+/[A-Za-z0-9_-]+$#', $partes['path'])) {
            return self::invalido($mensaje);
        }

        return self::valido();
    }

    private static function valido(): array
    {
        return ['ok' => true, 'estado' => 'ok', 'detalle' => null];
    }

    private static function invalido(string $detalle): array
    {
        return ['ok' => false, 'estado' => 'invalido_config', 'detalle' => $detalle];
    }
}

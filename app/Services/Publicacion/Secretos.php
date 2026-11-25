<?php

namespace App\Services\Publicacion;

use App\PublicacionCanal;

/**
 * Unico punto de descifrado, mascara y redaccion de credenciales
 * (REQ-SEG-01, SEG-03, SEG-04, SEG-05).
 *
 * `descifrar` nunca propaga: un canal cifrado con otra APP_KEY se degrada a
 * null y la corrida sigue con los demas (REQ-CAN-08).
 */
class Secretos
{
    /**
     * Credenciales descifradas del canal, o null si no se pueden leer.
     *
     * @return array|null
     */
    public static function descifrar(PublicacionCanal $canal)
    {
        try {
            $credenciales = $canal->credenciales;
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($credenciales) ? $credenciales : null;
    }

    /**
     * Mascara de presentacion: conserva la parte no secreta.
     *
     * Telegram: hasta el primer ':'. Discord: hasta el ultimo '/'.
     */
    public static function mascara(string $secreto): string
    {
        if ($secreto === '') {
            return '****';
        }

        $prefijo = self::prefijo($secreto);

        return $prefijo === '' ? '****' : $prefijo.'****';
    }

    /**
     * Reemplaza cada secreto y su prefijo visible, y trunca a 500.
     */
    public static function redactar(?string $texto, array $secretos = []): string
    {
        $texto = (string) $texto;

        $reemplazos = [];

        foreach ($secretos as $secreto) {
            if (!is_string($secreto) || $secreto === '') {
                continue;
            }

            $reemplazos[] = $secreto;

            $prefijo = self::prefijo($secreto);

            if ($prefijo !== '') {
                $reemplazos[] = $prefijo;
            }
        }

        usort($reemplazos, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        if ($reemplazos !== []) {
            $texto = str_replace($reemplazos, '****', $texto);
        }

        if (strlen($texto) > 500) {
            $texto = substr($texto, 0, 497).'...';
        }

        return $texto;
    }

    private static function prefijo(string $secreto): string
    {
        if (strpos($secreto, '://') !== false) {
            $corte = strrpos($secreto, '/');

            return $corte === false ? '' : substr($secreto, 0, $corte + 1);
        }

        $corte = strpos($secreto, ':');

        return $corte === false ? '' : substr($secreto, 0, $corte + 1);
    }
}

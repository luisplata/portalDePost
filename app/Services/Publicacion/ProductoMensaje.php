<?php

namespace App\Services\Publicacion;

use App\Producto;

/**
 * Composicion del mensaje comun a todos los drivers (REQ-DRV-03, DRV-06,
 * DRV-08, NFR-04).
 *
 * Nunca publica `hotLink`: el enlace de salida es siempre el redirect interno.
 * Los recortes se miden en unidades UTF-16, que es lo que cuentan Telegram y
 * Discord, y dejan margen para el prefijo del proveedor.
 */
class ProductoMensaje
{
    const LIMITE_CAPTION = 1024;
    const LIMITE_TITULO = 256;
    const LIMITE_TEXTO = 4096;
    const MARGEN = 16;

    public static function link(Producto $producto): string
    {
        return url('/redirect/'.$producto->id);
    }

    /**
     * Caption de Telegram: nombre recortado, salto de linea y link intacto.
     */
    public static function texto(Producto $producto): string
    {
        $link = self::link($producto);
        $disponible = self::LIMITE_CAPTION - self::MARGEN - self::utf16($link) - 1;

        return self::recortar((string) $producto->nombre, max(1, $disponible))
            ."\n".$link;
    }

    /**
     * Titulo de embed de Discord.
     */
    public static function titulo(Producto $producto): string
    {
        return self::recortar((string) $producto->nombre, self::LIMITE_TITULO);
    }

    /**
     * Recorta sin partir caracteres ni pares sustitutos, y agrega '...'.
     */
    public static function recortar(string $texto, int $maxUtf16): string
    {
        if (self::utf16($texto) <= $maxUtf16) {
            return $texto;
        }

        $presupuesto = max(0, $maxUtf16 - 3);

        $resultado = '';
        $unidades = 0;

        foreach (preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $caracter) {
            $largo = self::utf16($caracter);

            if ($unidades + $largo > $presupuesto) {
                break;
            }

            $resultado .= $caracter;
            $unidades += $largo;
        }

        return $resultado.'...';
    }

    /**
     * Una imagen publicable es http(s) y no el placeholder '#'.
     *
     * @param  mixed  $imagen
     */
    public static function imagenValida($imagen): bool
    {
        if (!is_string($imagen)) {
            return false;
        }

        $imagen = trim($imagen);

        if ($imagen === '' || $imagen === '#') {
            return false;
        }

        return (bool) preg_match('#^https?://#i', $imagen);
    }

    public static function utf16(string $texto): int
    {
        return intdiv(strlen(mb_convert_encoding($texto, 'UTF-16LE', 'UTF-8')), 2);
    }
}

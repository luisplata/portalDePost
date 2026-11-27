<?php

namespace App\Services\Publicacion;

use App\Producto;
use Illuminate\Support\Facades\Http;

/**
 * Driver de Discord via webhook (REQ-DRV-05, DRV-06, DRV-07, DRV-08, DRV-10,
 * DRV-11).
 *
 * Publica un embed con titulo, link interno e imagen; si la imagen no es
 * publicable, omite el bloque `image`. Nunca propaga excepciones.
 */
class DiscordDriver implements CanalDriver
{
    /** @var ClasificadorTransporte */
    private $clasificador;

    public function __construct(ClasificadorTransporte $clasificador = null)
    {
        $this->clasificador = $clasificador ?: new ClasificadorTransporte();
    }

    public function tipo(): string
    {
        return 'discord';
    }

    public function validar(array $credenciales): array
    {
        return Credenciales::validar('discord', $credenciales);
    }

    public function enviar(Producto $producto, array $credenciales, string $link): ResultadoEntrega
    {
        $webhook = isset($credenciales['webhook_url'])
            ? (string) $credenciales['webhook_url']
            : '';
        $secretos = [$webhook];

        $embed = [
            'title' => ProductoMensaje::titulo($producto),
            'url' => $link,
        ];

        if (ProductoMensaje::imagenValida($producto->imagen)) {
            $embed['image'] = ['url' => $producto->imagen];

            if (strlen(json_encode($embed)) > 6000) {
                unset($embed['image']);
            }
        }

        try {
            $respuesta = $this->http()->post($webhook, ['embeds' => [$embed]]);

            return $this->clasificarRespuesta($respuesta, $webhook, $secretos);
        } catch (\Throwable $error) {
            return $this->clasificador->clasificar($error, $secretos);
        }
    }

    private function http()
    {
        return Http::withOptions([
            'connect_timeout' => config('publicaciones.connect_timeout'),
            'timeout' => config('publicaciones.read_timeout'),
        ]);
    }

    private function clasificarRespuesta($respuesta, string $webhook, array $secretos): ResultadoEntrega
    {
        $status = $respuesta->status();
        $json = $respuesta->json();

        if (in_array($status, [200, 204], true)) {
            $referencia = is_array($json) && isset($json['id']) ? (string) $json['id'] : null;

            return ResultadoEntrega::entregado($status, $referencia);
        }

        if ($status === 429) {
            $espera = $this->esperaDeRetryAfter($respuesta, $json);

            return ResultadoEntrega::temporal($status, $espera, 'rate_limited:'.$espera.'s');
        }

        if (in_array($status, [401, 403, 404], true)) {
            return ResultadoEntrega::permanente(
                $status,
                Secretos::redactar('credencial de Discord rechazada', $secretos),
                true
            );
        }

        if ($status >= 500) {
            return ResultadoEntrega::servidor($status, 'http_'.$status);
        }

        if ($status >= 400) {
            return ResultadoEntrega::permanente($status, 'http_'.$status);
        }

        return ResultadoEntrega::servidor($status, 'respuesta ilegible');
    }

    private function esperaDeRetryAfter($respuesta, $json): int
    {
        if (is_array($json) && isset($json['retry_after'])) {
            return max(1, (int) ceil((float) $json['retry_after']));
        }

        $header = (string) $respuesta->header('Retry-After');

        if ($header !== '') {
            if (is_numeric($header)) {
                return max(1, (int) ceil((float) $header));
            }

            $instante = strtotime($header);

            if ($instante !== false) {
                return max(1, $instante - time());
            }
        }

        return (int) config('publicaciones.espera_default');
    }
}

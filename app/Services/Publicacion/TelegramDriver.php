<?php

namespace App\Services\Publicacion;

use App\Producto;
use Illuminate\Support\Facades\Http;

/**
 * Driver de Telegram (REQ-DRV-02, DRV-03, DRV-04, DRV-08, DRV-10, DRV-11).
 *
 * Envia sendPhoto con el caption literal (sin parse_mode) y cae una unica vez
 * a sendMessage cuando la foto falla o la imagen no es publicable. Nunca
 * propaga excepciones: las clasifica y redacta los secretos.
 */
class TelegramDriver implements CanalDriver
{
    const ENDPOINT = 'https://api.telegram.org';

    /** @var ClasificadorTransporte */
    private $clasificador;

    public function __construct(ClasificadorTransporte $clasificador = null)
    {
        $this->clasificador = $clasificador ?: new ClasificadorTransporte();
    }

    public function tipo(): string
    {
        return 'telegram';
    }

    public function validar(array $credenciales): array
    {
        return Credenciales::validar('telegram', $credenciales);
    }

    public function enviar(Producto $producto, array $credenciales, string $link): ResultadoEntrega
    {
        $token = isset($credenciales['token']) ? (string) $credenciales['token'] : '';
        $chatId = isset($credenciales['chat_id']) ? (string) $credenciales['chat_id'] : '';
        $secretos = [$token, $chatId];
        $base = self::ENDPOINT.'/bot'.$token;

        try {
            if (ProductoMensaje::imagenValida($producto->imagen)) {
                $respuesta = $this->http()->asForm()->post($base.'/sendPhoto', [
                    'chat_id' => $chatId,
                    'photo' => $producto->imagen,
                    'caption' => ProductoMensaje::texto($producto, $link),
                    'disable_notification' => false,
                ]);

                if ($respuesta->status() !== 400) {
                    return $this->clasificarRespuesta($respuesta, $secretos);
                }
            }

            $respuesta = $this->http()->asForm()->post($base.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => ProductoMensaje::texto($producto, $link),
                'disable_notification' => false,
            ]);

            return $this->clasificarRespuesta($respuesta, $secretos);
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

    private function clasificarRespuesta($respuesta, array $secretos): ResultadoEntrega
    {
        $status = $respuesta->status();
        $json = $respuesta->json();

        if ($status === 200 && is_array($json) && isset($json['ok']) && $json['ok'] === true) {
            $referencia = isset($json['result']['message_id'])
                ? (string) $json['result']['message_id']
                : null;

            return ResultadoEntrega::entregado($status, $referencia);
        }

        if ($status === 429) {
            $espera = isset($json['parameters']['retry_after'])
                ? (int) $json['parameters']['retry_after']
                : (int) config('publicaciones.espera_default');

            return ResultadoEntrega::temporal($status, $espera, 'rate_limited:'.$espera.'s');
        }

        if (in_array($status, [401, 403, 404], true)) {
            return ResultadoEntrega::permanente(
                $status,
                Secretos::redactar('credencial de Telegram rechazada', $secretos),
                true
            );
        }

        $detalle = $this->detalle($json, $status, $secretos);

        if ($status >= 500 || $status === 200) {
            return ResultadoEntrega::servidor($status, $detalle);
        }

        return ResultadoEntrega::permanente($status, $detalle);
    }

    private function detalle($json, int $status, array $secretos): string
    {
        $detalle = is_array($json) && isset($json['description'])
            ? (string) $json['description']
            : 'http_'.$status;

        return Secretos::redactar($detalle, $secretos);
    }
}

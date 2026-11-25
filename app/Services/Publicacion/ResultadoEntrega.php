<?php

namespace App\Services\Publicacion;

/**
 * Resultado normalizado de un intento de entrega (REQ-DRV-01, DRV-11).
 *
 * El error siempre sale redactado y truncado a 500: los drivers nunca exponen
 * credenciales, y el servicio persiste este texto tal cual.
 */
final class ResultadoEntrega
{
    const ENTREGADO = 'entregado';
    const TEMPORAL = 'rechazado_temporal';
    const PERMANENTE = 'rechazado_permanente';
    const SERVIDOR = 'fallo_servidor';
    const AMBIGUO = 'ambiguo';

    /** @var string */
    public $resultado;

    /** @var int|null */
    public $httpStatus;

    /** @var string|null */
    public $referencia;

    /** @var string|null */
    public $error;

    /** @var int|null Segundos pedidos por un 429. */
    public $espera;

    /** @var bool */
    public $invalidaCredencial;

    private function __construct(
        string $resultado,
        $httpStatus,
        $referencia,
        $error,
        $espera,
        bool $invalidaCredencial
    ) {
        $this->resultado = $resultado;
        $this->httpStatus = $httpStatus;
        $this->referencia = $referencia;
        $this->error = $error === null ? null : Secretos::redactar($error);
        $this->espera = $espera;
        $this->invalidaCredencial = $invalidaCredencial;
    }

    public static function entregado($http, $referencia = null): self
    {
        return new self(self::ENTREGADO, $http, $referencia, null, null, false);
    }

    public static function temporal($http, $espera, $error): self
    {
        return new self(self::TEMPORAL, $http, null, $error, $espera, false);
    }

    public static function permanente($http, $error, bool $invalidaCredencial = false): self
    {
        return new self(self::PERMANENTE, $http, null, $error, null, $invalidaCredencial);
    }

    public static function servidor($http, $error): self
    {
        return new self(self::SERVIDOR, $http, null, $error, null, false);
    }

    public static function ambiguo($http, $error): self
    {
        return new self(self::AMBIGUO, $http, null, $error, null, false);
    }
}

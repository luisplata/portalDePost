<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ajustes clave/valor de publicaciones (REQ-AJUS-01..06, OBS-07).
 *
 * La ausencia de una clave es fail-closed: el interruptor queda apagado y el
 * corte nulo. Nunca se cachea: cada corrida lee la base.
 */
class PublicacionAjuste extends Model
{
    const CLAVE_ACTIVO = 'publicaciones_enabled';
    const CLAVE_DESDE = 'publicaciones_desde';

    const CLAVES_RESUMEN = [
        'corrida_at',
        'corrida_sembradas',
        'corrida_enviadas',
        'corrida_fallidas',
        'corrida_inciertas',
        'corrida_duracion_ms',
    ];

    protected $table = 'publicacion_ajustes';

    protected $fillable = ['clave', 'valor'];

    /**
     * Interruptor maestro. Solo el valor exacto '1' lo enciende.
     */
    public static function activo(): bool
    {
        return static::valorDe(self::CLAVE_ACTIVO) === '1';
    }

    /**
     * Corte global en UTC, o null si falta o es invalido.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public static function desde()
    {
        $valor = static::valorDe(self::CLAVE_DESDE);

        if ($valor === null || trim($valor) === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor, 'UTC');

                if ($fecha !== false) {
                    return $fecha;
                }
            } catch (\Exception $e) {
                // Se prueba el siguiente formato.
            }
        }

        return null;
    }

    /**
     * Resumen de la ultima corrida completada. Sin corrida, todo en cero.
     *
     * @return array{corrida_at: \Illuminate\Support\Carbon|null, sembradas: int, enviadas: int, fallidas: int, inciertas: int, duracion_ms: int}
     */
    public static function resumenCorrida(): array
    {
        $valores = static::query()
            ->whereIn('clave', self::CLAVES_RESUMEN)
            ->pluck('valor', 'clave');

        $corridaAt = $valores['corrida_at'] ?? null;

        if ($corridaAt !== null && trim($corridaAt) !== '') {
            try {
                $corridaAt = Carbon::createFromFormat('Y-m-d H:i:s', $corridaAt, 'UTC');
            } catch (\Exception $e) {
                $corridaAt = null;
            }
        } else {
            $corridaAt = null;
        }

        return [
            'corrida_at' => $corridaAt,
            'sembradas' => (int) ($valores['corrida_sembradas'] ?? 0),
            'enviadas' => (int) ($valores['corrida_enviadas'] ?? 0),
            'fallidas' => (int) ($valores['corrida_fallidas'] ?? 0),
            'inciertas' => (int) ($valores['corrida_inciertas'] ?? 0),
            'duracion_ms' => (int) ($valores['corrida_duracion_ms'] ?? 0),
        ];
    }

    /**
     * Guarda el resumen de una corrida real (upsert por clave).
     */
    public static function registrarCorrida(array $resumen): void
    {
        foreach (self::CLAVES_RESUMEN as $clave) {
            if (!array_key_exists($clave, $resumen)) {
                continue;
            }

            $valor = $resumen[$clave];

            if ($valor instanceof \DateTimeInterface) {
                $valor = Carbon::instance($valor)->setTimezone('UTC')->format('Y-m-d H:i:s');
            }

            static::poner($clave, $valor === null ? null : (string) $valor);
        }
    }

    /**
     * Upsert de una clave. Cualquier valor se guarda como texto.
     *
     * @param  string  $clave
     * @param  mixed  $valor
     */
    public static function poner(string $clave, $valor): void
    {
        static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor === null ? null : (string) $valor]
        );
    }

    private static function valorDe(string $clave)
    {
        return static::query()->where('clave', $clave)->value('valor');
    }
}

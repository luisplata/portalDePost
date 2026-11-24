<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Fuente unica del dia operativo de analitica.
 *
 * `config('analytics.timezone')` (America/Bogota, UTC-5 fijo) define el dia de
 * negocio; `config('app.timezone')` sigue en UTC. MySQL nunca calcula el dia:
 * sin CURDATE() ni CONVERT_TZ.
 */
class AnalyticsTimeService
{
    /**
     * @return string
     */
    public function timezone()
    {
        return config('analytics.timezone', 'America/Bogota');
    }

    /**
     * Fecha local de hoy (Y-m-d).
     *
     * @return string
     */
    public function today()
    {
        return Carbon::now($this->timezone())->toDateString();
    }

    /**
     * Convierte un instante UTC a fecha local.
     *
     * @param  \DateTimeInterface|string  $utcInstant
     * @return string
     */
    public function toLocalDate($utcInstant)
    {
        return Carbon::parse($utcInstant, 'UTC')->tz($this->timezone())->toDateString();
    }

    /**
     * Primer instante UTC del dia local (inclusivo).
     *
     * @param  string  $date
     * @return \Illuminate\Support\Carbon
     */
    public function dayStartUtc($date)
    {
        return Carbon::parse($date.' 00:00:00', $this->timezone())->utc();
    }

    /**
     * Primer instante UTC del dia siguiente (exclusivo).
     *
     * @param  string  $date
     * @return \Illuminate\Support\Carbon
     */
    public function dayEndUtc($date)
    {
        return Carbon::parse($date.' 00:00:00', $this->timezone())->addDay()->utc();
    }

    /**
     * Lista inclusiva de dias entre dos fechas locales.
     *
     * @param  string  $from
     * @param  string  $to
     * @return array
     */
    public function dates($from, $to)
    {
        $dates = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * Normaliza un rango `[from, to]`: default de 30 dias y recorte a 365.
     *
     * @param  string|null  $from
     * @param  string|null  $to
     * @return array
     */
    public function range($from = null, $to = null)
    {
        $to = $to ?: $this->today();
        $from = $from ?: Carbon::parse($to)->subDays(29)->toDateString();

        $limit = Carbon::parse($to)->subDays(364)->toDateString();

        if ($from < $limit) {
            $from = $limit;
        }

        return ['from' => $from, 'to' => $to];
    }
}

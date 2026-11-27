<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Agregado diario de impresiones y clicks.
 *
 * El pluralizador de Eloquent derivaria `ad_stat_dailies`, asi que el nombre
 * de tabla es explicito. `date` es una fecha de calendario local (America/
 * Bogota), sin zona: se guarda como DATE y se calcula en PHP.
 */
class AdStatDaily extends Model
{
    protected $table = 'ad_stats_daily';

    protected $fillable = [
        'creative_id',
        'placement_id',
        'date',
        'impressions',
        'clicks',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function creative()
    {
        return $this->belongsTo(AdCreative::class, 'creative_id');
    }

    public function placement()
    {
        return $this->belongsTo(AdPlacement::class, 'placement_id');
    }
}

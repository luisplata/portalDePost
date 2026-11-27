<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Hecho diario por producto. `date` es una fecha local (America/Bogota), sin
 * zona: se guarda como DATE y se calcula en PHP. `unique_visits` nulo marca
 * un dia sin captura de identidad (backfill o solo click).
 */
class PostStatDaily extends Model
{
    protected $table = 'post_stats_daily';

    protected $fillable = [
        'producto_id',
        'date',
        'visits',
        'unique_visits',
        'clicks',
    ];

    protected $casts = [
        'producto_id' => 'integer',
        'visits' => 'integer',
        'unique_visits' => 'integer',
        'clicks' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}

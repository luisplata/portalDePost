<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Creatividad snapshot de un anuncio (REQ-CRT-01..04).
 *
 * No depende de datos vivos de otra tabla para renderizarse: todo lo visible
 * (imagen, headline, alt, CTA, destino) vive en esta fila. `producto_id` es
 * solo una referencia opcional para el formulario del admin.
 */
class AdCreative extends Model
{
    protected $fillable = [
        'campaign_key',
        'name',
        'placement_id',
        'status',
        'starts_at',
        'ends_at',
        'priority',
        'image_url',
        'alt_text',
        'headline',
        'body',
        'cta_label',
        'target_url',
        'producto_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'producto_id' => 'integer',
    ];

    public function placement()
    {
        return $this->belongsTo(AdPlacement::class, 'placement_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function stats()
    {
        return $this->hasMany(AdStatDaily::class, 'creative_id');
    }

    /**
     * Ventana en hora local para los inputs datetime-local del admin (D-25).
     *
     * @return string|null  Y-m-d\TH:i
     */
    public function getStartsAtLocalAttribute()
    {
        return $this->localInput($this->starts_at);
    }

    /**
     * @return string|null  Y-m-d\TH:i
     */
    public function getEndsAtLocalAttribute()
    {
        return $this->localInput($this->ends_at);
    }

    /**
     * @param  \Illuminate\Support\Carbon|null  $value
     * @return string|null
     */
    private function localInput($value)
    {
        if ($value === null) {
            return null;
        }

        return $value->copy()->tz(config('ads.timezone', 'America/Bogota'))->format('Y-m-d\TH:i');
    }
}

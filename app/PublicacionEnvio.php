<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Entrega de un producto a un canal. El par producto/canal es unico en
 * cualquier estado (REQ-ENT-03); el canal se resuelve incluso eliminado para
 * conservar el historial legible (REQ-CAN-06, OBS-02).
 */
class PublicacionEnvio extends Model
{
    const PENDIENTE = 'pendiente';
    const ENVIADO = 'enviado';
    const FALLIDO = 'fallido';
    const INCIERTO = 'incierto';

    protected $table = 'publicacion_envios';

    protected $fillable = [
        'producto_id',
        'publicacion_canal_id',
        'canal_tipo',
        'estado',
        'intentos',
        'proximo_intento_at',
        'enviado_at',
        'referencia_externa',
        'http_status',
        'error',
    ];

    protected $casts = [
        'intentos' => 'integer',
        'proximo_intento_at' => 'datetime',
        'enviado_at' => 'datetime',
        'http_status' => 'integer',
    ];

    public function canal()
    {
        return $this->belongsTo(PublicacionCanal::class, 'publicacion_canal_id')
            ->withTrashed();
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}

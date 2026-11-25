<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Canal externo configurable (Telegram, Discord).
 *
 * El nombre es unico incluso entre eliminados; el borrado es logico y conserva
 * el historial de entregas (REQ-CAN-06, REQ-CAN-12).
 */
class PublicacionCanal extends Model
{
    use SoftDeletes;

    protected $table = 'publicacion_canales';

    protected $fillable = [
        'tipo',
        'nombre',
        'habilitado',
        'vigente_desde',
        'credenciales',
        'config',
        'estado',
        'estado_detalle',
        'estado_verificado_at',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'vigente_desde' => 'datetime',
        'config' => 'array',
        'estado_verificado_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function envios()
    {
        return $this->hasMany(PublicacionEnvio::class, 'publicacion_canal_id');
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Evento de click saliente. Inmutable: solo `created_at` en UTC.
 */
class PostClick extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'post_clicks';

    protected $fillable = [
        'producto_id',
        'date',
        'identity',
        'source',
        'referer',
        'user_agent',
        'ip_hash',
    ];

    protected $casts = [
        'producto_id' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}

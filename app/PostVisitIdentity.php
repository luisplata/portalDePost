<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Identidad anonima que visito un producto en un dia local. Inmutable: solo
 * `created_at` en UTC.
 */
class PostVisitIdentity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'post_visit_identities';

    protected $fillable = [
        'producto_id',
        'date',
        'identity',
    ];

    protected $casts = [
        'producto_id' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}

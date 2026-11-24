<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Detalle de click (REQ-PRV-01).
 *
 * Solo `created_at` en UTC: no hay `updated_at` porque la fila es inmutable.
 */
class AdClick extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'creative_id',
        'placement_id',
        'anonymous_user_uuid',
        'referer',
        'ip_hash',
        'user_agent',
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

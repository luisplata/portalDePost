<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Slot administrable de publicidad (REQ-PLC-01).
 *
 * `key` es parte del contrato de vistas y API: inmutable despues de crear.
 */
class AdPlacement extends Model
{
    protected $fillable = ['key', 'name', 'max_items', 'is_active'];

    protected $casts = [
        'max_items' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creatives()
    {
        return $this->hasMany(AdCreative::class, 'placement_id');
    }
}

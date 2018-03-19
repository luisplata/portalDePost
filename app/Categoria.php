<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    //
    public function categoria()
    {
        return $this->belongsTo('App\Categoria',"padre");
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VisitPost extends Model
{
    //
    public function Post(){
        return $this->hasOne(Producto::class);
    }

    public function AddVisita(){
        $this->visita++;
        $this->save();
    }

    public function AddHotLink(){
        $this->idoAlPack++;
        $this->save();
    }
}

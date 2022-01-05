<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitStream extends Model
{
    use HasFactory;
    
    //
    public function Stream(){
        return $this->hasOne(Stream::class);
    }

    public function AddVisita(){
        $this->visita++;
        $this->save();
    }
}

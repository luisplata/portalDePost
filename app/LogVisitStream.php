<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogVisitStream extends Model
{
    use HasFactory;
    public function Post(){
        return $this->hasOne(Stream::class);
    }
}

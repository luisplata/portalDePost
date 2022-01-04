<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    use HasFactory;

    public static function GetFirstStreams(){
        return Stream::where("estado","1")->where('publication_date', '<', date("Y-m-d H:i:s"))->orderBy('publication_date', 'desc')->paginate(10);
    }
}

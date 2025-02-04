<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnonymousUsers extends Model
{
    use HasFactory;
    //
    protected $fillable = [
        'uuid_user',
    ];
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $hidden = ['hotLink','publication_date','created_at','updated_at','id','imagen','categorias_id','estado'];
    public function categoria() {
        return $this->belongsTo('App\Categoria',"categorias_id");
    }
    public static function PostOfBanner(){
        return Producto::where("estado","1")->where('publication_date', '<', date("Y-m-d H:i:s"))->limit(6)->orderBy('publication_date', 'desc')->get();
    }
    public static function PostOfHot(){
        return Producto::where("estado","1")->where('publication_date', '<', date("Y-m-d H:i:s"))->limit(3)->get();
    }
    public static function PostOfPopular(){
        return Producto::where("estado","1")->where('publication_date', '<', date("Y-m-d H:i:s"))->limit(3)->get();
    }
    public static function PostOfPacks(){
        return Producto::where("estado","1")->where('publication_date', '<', date("Y-m-d H:i:s"))->paginate(10);
    }

    public function ConvertNameNormalToUrl(){
        return str_replace(" ", "-", $this->nombre);
    }
    public function Visitas(){
        return $this->hasOne(VisitPost::class);
    }
    public function LogVisitas(){
        return $this->hasMany(LogVisit::class);
    }
}

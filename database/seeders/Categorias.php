<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class Categorias extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        //Creamos la categoria Root
        $categoria = new \App\Categoria();
        $categoria->nombre = "Productos";
        $categoria->descripcion = "Categoria para los productos";
        $categoria->padre = 1;
        $categoria->id = 1;
        $categoria->save();
        
        
        $categoria = new \App\CategoriaPost();
        $categoria->nombre = "Post";
        $categoria->descripcion = "categorias para los post";
        $categoria->padre = 1;
        $categoria->id = 1;
        $categoria->save();
    }

}

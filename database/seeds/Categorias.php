<?php

use Illuminate\Database\Seeder;

class Categorias extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        //Creamos la categoria Root
        $categoria = new App\Categoria();
        $categoria->nombre = "Root";
        $categoria->descripcion = "Categoria Inicial de donde todos son hijos";
        $categoria->padre = -1;
        $categoria->save();
    }

}

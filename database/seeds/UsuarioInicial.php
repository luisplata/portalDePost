<?php

use Illuminate\Database\Seeder;

class UsuarioInicial extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        //creamos al usuario root
        $userRoot = new App\Usuario();
        $userRoot->id = 1;
        $userRoot->nombre = "Root";
        $userRoot->email = "www.luisplata@gmail.com";
        $userRoot->telefono = "3015086264";
        $userRoot->pass = sha1("luisplata");
        $userRoot->save();
    }

}

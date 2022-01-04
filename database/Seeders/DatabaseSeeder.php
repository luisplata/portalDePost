<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);
        $this->call(UsuarioInicial::class);
        $this->call(Categorias::class);
        factory(\App\Producto::class, 200)->create()->each(function ($u) {
            $u->save();
        });
        \App\Stream::factory()->count(200)->create();
        /*factory(\App\Stream::class, 200)->create()->each(function ($u) {
            $u->save();
        });*/
    }
}

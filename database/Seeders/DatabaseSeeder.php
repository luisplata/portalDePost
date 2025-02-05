<?php

namespace Database\Seeders;

use App\Producto;
use App\Stream;
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
        $this->call(UsuarioInicial::class);
        $this->call(Categorias::class);
        factory(Producto::class, 200)->create()->each(function ($u) {
            $u->save();
        });
        Stream::factory()->count(200)->create();
    }
}
